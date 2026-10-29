<?php

// Domain behavioural suite — Metadata (spec: metadata-domain-spec.md).

use App\Domains\Accounts\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\deleteJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\putJson;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    $user = User::where('role', 'super admin')->first();
    $this->companyId = $user->companies()->first()->id;
    $this->withHeaders(['company' => $this->companyId]);
    Sanctum::actingAs($user, ['*']);
});

it('generates colliding slugs with suffixes and freezes them across renames', function () {
    $a = postJson('/api/v1/custom-fields', ['name' => 'vat number', 'label' => 'VAT Number',
        'model_type' => 'Customer', 'order' => 1, 'type' => 'Input', 'is_required' => false])
        ->assertSuccessful()->json('data');
    $b = postJson('/api/v1/custom-fields', ['name' => 'vat number', 'label' => 'VAT Number Two',
        'model_type' => 'Customer', 'order' => 2, 'type' => 'Input', 'is_required' => false])
        ->assertSuccessful()->json('data');

    expect($a['slug'])->toBe('CUSTOM_CUSTOMER_VAT_NUMBER');
    expect($b['slug'])->toBe('CUSTOM_CUSTOMER_VAT_NUMBER_1');

    putJson("/api/v1/custom-fields/{$a['id']}", ['name' => 'renamed', 'label' => 'Totally Renamed',
        'model_type' => 'Customer', 'order' => 1, 'type' => 'Input', 'is_required' => false])
        ->assertSuccessful();
    expect(DB::table('custom_fields')->where('id', $a['id'])->value('slug'))
        ->toBe('CUSTOM_CUSTOMER_VAT_NUMBER');
});

it('normalises time default answers and round-trips per type', function () {
    postJson('/api/v1/custom-fields', ['name' => 'opens at', 'label' => 'Opens At',
        'model_type' => 'Customer', 'order' => 1, 'type' => 'Time', 'is_required' => false,
        'default_answer' => '9:30 AM'])->assertSuccessful();
    expect(DB::table('custom_fields')->where('name', 'opens at')->value('time_answer'))->toBe('09:30:00');
});

it('attaches and updates owner values in the mapped column, deleting them with the definition', function () {
    $field = postJson('/api/v1/custom-fields', ['name' => 'nick', 'label' => 'Nickname',
        'model_type' => 'Customer', 'order' => 1, 'type' => 'Input', 'is_required' => false])->json('data');

    $customerId = postJson('/api/v1/customers', ['name' => 'Fielded',
        'customFields' => [['id' => $field['id'], 'value' => 'Neo']]])->assertSuccessful()->json('data.id');
    $value = DB::table('custom_field_values')->where('custom_field_id', $field['id'])->first();
    expect($value->string_answer)->toBe('Neo');
    expect((int) $value->custom_field_valuable_id)->toBe($customerId);

    putJson("/api/v1/customers/{$customerId}", ['name' => 'Fielded',
        'customFields' => [['id' => $field['id'], 'value' => 'Morpheus']]])->assertSuccessful();
    expect(DB::table('custom_field_values')->where('custom_field_id', $field['id'])->count())->toBe(1);
    expect(DB::table('custom_field_values')->where('custom_field_id', $field['id'])->value('string_answer'))
        ->toBe('Morpheus');

    deleteJson("/api/v1/custom-fields/{$field['id']}")->assertOk();
    expect(DB::table('custom_field_values')->where('custom_field_id', $field['id'])->count())->toBe(0);
    expect(DB::table('custom_fields')->where('id', $field['id'])->exists())->toBeFalse();
});

it('scopes note name uniqueness by company and type', function () {
    postJson('/api/v1/notes', ['type' => 'Invoice', 'name' => 'Thanks', 'notes' => 'Thank you!', 'is_default' => false])
        ->assertSuccessful();
    postJson('/api/v1/notes', ['type' => 'Invoice', 'name' => 'Thanks', 'notes' => 'Again', 'is_default' => false])
        ->assertStatus(422)->assertJsonValidationErrors(['name']);
    postJson('/api/v1/notes', ['type' => 'Estimate', 'name' => 'Thanks', 'notes' => 'Estimate note', 'is_default' => false])
        ->assertSuccessful();
});

it('demotes other default notes of the type across companies — the known defect', function () {
    $otherCompany = DB::table('companies')->insertGetId([
        'name' => 'Other Co', 'slug' => 'other-co', 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('notes')->insert([
        'type' => 'Invoice', 'name' => 'Their default', 'notes' => 'x', 'is_default' => true,
        'company_id' => $otherCompany, 'created_at' => now(), 'updated_at' => now(),
    ]);

    postJson('/api/v1/notes', ['type' => 'Invoice', 'name' => 'Our default', 'notes' => 'y', 'is_default' => true])
        ->assertSuccessful();

    expect((bool) DB::table('notes')->where('company_id', $otherCompany)->value('is_default'))->toBeFalse();
});
