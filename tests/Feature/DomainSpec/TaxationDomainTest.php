<?php

// Domain behavioural suite — Taxation (spec: taxation-domain-spec.md).

use App\Domains\Accounts\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\deleteJson;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\putJson;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    $user = User::where('role', 'super admin')->first();
    $this->companyId = $user->companies()->first()->id;
    $this->withHeaders(['company' => $this->companyId]);
    Sanctum::actingAs($user, ['*']);
});

it('enforces name uniqueness among the company general kinds', function () {
    postJson('/api/v1/tax-types', ['name' => 'VAT', 'calculation_type' => 'percentage', 'percent' => 18])
        ->assertSuccessful();
    postJson('/api/v1/tax-types', ['name' => 'VAT', 'calculation_type' => 'percentage', 'percent' => 5])
        ->assertStatus(422)->assertJsonValidationErrors(['name']);
});

it('applies the create defaults and forces kind and company', function () {
    $created = postJson('/api/v1/tax-types', [
        'name' => 'Defaulted', 'calculation_type' => 'percentage', 'percent' => 10,
        'type' => 'MODULE', 'company_id' => 999,
    ])->assertSuccessful()->json('data');

    expect($created['transaction_type'])->toBe('sales');
    expect($created['compound_tax'])->toBeFalsy();
    expect($created['type'])->toBe('GENERAL');
    expect((int) $created['company_id'])->toBe((int) $this->companyId);
});

it('allows compound only for percentage sales taxes, honouring stored values on update', function () {
    postJson('/api/v1/tax-types', [
        'name' => 'FixedCompound', 'calculation_type' => 'fixed', 'fixed_amount' => 500, 'compound_tax' => true,
    ])->assertStatus(422)->assertJsonValidationErrors(['compound_tax']);

    postJson('/api/v1/tax-types', [
        'name' => 'PurchCompound', 'calculation_type' => 'percentage', 'percent' => 5,
        'transaction_type' => 'purchases', 'compound_tax' => true,
    ])->assertStatus(422)->assertJsonValidationErrors(['compound_tax']);

    $id = postJson('/api/v1/tax-types', [
        'name' => 'Compound', 'calculation_type' => 'percentage', 'percent' => 5, 'compound_tax' => true,
    ])->assertSuccessful()->json('data.id');

    // Update inheriting the stored compound flag: switching to fixed must be refused.
    putJson("/api/v1/tax-types/{$id}", ['name' => 'Compound', 'calculation_type' => 'fixed', 'fixed_amount' => 100])
        ->assertStatus(422)->assertJsonValidationErrors(['compound_tax']);
});

it('keeps applied taxes as snapshots and blocks deletion while referenced', function () {
    $typeId = postJson('/api/v1/tax-types', [
        'name' => 'SnapTax', 'calculation_type' => 'percentage', 'percent' => 10,
    ])->assertSuccessful()->json('data.id');

    $itemId = postJson('/api/v1/items', [
        'name' => 'Taxed item', 'price' => 1000,
        'taxes' => [['tax_type_id' => $typeId, 'name' => 'SnapTax', 'percent' => 10, 'amount' => 100]],
    ])->assertSuccessful()->json('data.id');

    deleteJson("/api/v1/tax-types/{$typeId}")
        ->assertStatus(422)->assertJson(['error' => 'taxes_attached']);

    putJson("/api/v1/tax-types/{$typeId}", ['name' => 'SnapTax renamed', 'calculation_type' => 'percentage', 'percent' => 25])
        ->assertSuccessful();
    $applied = DB::table('taxes')->where('item_id', $itemId)->first();
    expect($applied->name)->toBe('SnapTax');
    expect((float) $applied->percent)->toBe(10.0);

    // Clearing the references frees the type for deletion.
    putJson("/api/v1/items/{$itemId}", ['name' => 'Taxed item', 'price' => 1000, 'taxes' => []])
        ->assertSuccessful();
    deleteJson("/api/v1/tax-types/{$typeId}")->assertOk()->assertJson(['success' => true]);
});

it('lists only the company general kinds with search', function () {
    postJson('/api/v1/tax-types', ['name' => 'Alpha VAT', 'calculation_type' => 'percentage', 'percent' => 1])->assertSuccessful();
    postJson('/api/v1/tax-types', ['name' => 'Beta GST', 'calculation_type' => 'percentage', 'percent' => 2])->assertSuccessful();
    DB::table('tax_types')->insert([
        'name' => 'Module tax', 'calculation_type' => 'percentage', 'percent' => 3,
        'type' => 'MODULE', 'transaction_type' => 'sales', 'company_id' => $this->companyId,
        'compound_tax' => 0, 'collective_tax' => 0, 'created_at' => now(), 'updated_at' => now(),
    ]);

    $names = collect(getJson('/api/v1/tax-types?limit=all')->assertOk()->json('data'))->pluck('name');
    expect($names)->toContain('Alpha VAT', 'Beta GST')->not->toContain('Module tax');

    $found = collect(getJson('/api/v1/tax-types?limit=all&search=Beta')->json('data'))->pluck('name');
    expect($found->all())->toBe(['Beta GST']);
});
