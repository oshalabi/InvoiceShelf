<?php

// Domain behavioural suite — Purchases (spec: purchases-domain-spec.md).

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
    $this->categoryId = postJson('/api/v1/categories', ['name' => 'Office'])->json('data.id');
    $this->companyCurrency = (int) DB::table('company_settings')->where('company_id', $this->companyId)
        ->where('option', 'currency')->value('value');
    $this->usd = DB::table('currencies')->where('code', 'USD')->value('id');
});

it('stores the submitted currency and applies the exchange-rate rules', function () {
    postJson('/api/v1/expenses', [
        'expense_date' => '2026-02-01', 'expense_category_id' => $this->categoryId,
        'amount' => 500, 'currency_id' => $this->usd,
    ])->assertStatus(422)->assertJsonValidationErrors(['exchange_rate']);

    $foreign = postJson('/api/v1/expenses', [
        'expense_date' => '2026-02-01', 'expense_category_id' => $this->categoryId,
        'amount' => 500, 'currency_id' => $this->usd, 'exchange_rate' => 3,
    ])->assertSuccessful()->json('data');
    $row = DB::table('expenses')->where('id', $foreign['id'])->first();
    expect((int) $row->currency_id)->toBe((int) $this->usd);
    expect((int) $row->base_amount)->toBe(1500);

    $home = postJson('/api/v1/expenses', [
        'expense_date' => '2026-02-01', 'expense_category_id' => $this->categoryId,
        'amount' => 500, 'currency_id' => (string) $this->companyCurrency,
    ])->assertSuccessful()->json('data');
    expect((int) DB::table('expenses')->where('id', $home['id'])->value('base_amount'))->toBe(500);
});

it('treats the tax list as absent-keeps, empty-clears, present-replaces — purchases types only', function () {
    $purchType = postJson('/api/v1/tax-types', ['name' => 'PTax', 'calculation_type' => 'percentage',
        'percent' => 10, 'transaction_type' => 'purchases'])->json('data.id');
    $salesType = postJson('/api/v1/tax-types', ['name' => 'STax', 'calculation_type' => 'percentage',
        'percent' => 10])->json('data.id');

    postJson('/api/v1/expenses', [
        'expense_date' => '2026-02-02', 'expense_category_id' => $this->categoryId,
        'amount' => 100, 'currency_id' => (string) $this->companyCurrency,
        'taxes' => [['tax_type_id' => $salesType, 'amount' => 10]],
    ])->assertStatus(422);

    $id = postJson('/api/v1/expenses', [
        'expense_date' => '2026-02-02', 'expense_category_id' => $this->categoryId,
        'amount' => 100, 'currency_id' => (string) $this->companyCurrency,
        'taxes' => [['tax_type_id' => $purchType, 'amount' => 10]],
    ])->assertSuccessful()->json('data.id');
    expect(DB::table('taxes')->where('expense_id', $id)->count())->toBe(1);
    expect(DB::table('taxes')->where('expense_id', $id)->value('name'))->toBe('PTax');

    putJson("/api/v1/expenses/{$id}", [
        'expense_date' => '2026-02-02', 'expense_category_id' => $this->categoryId,
        'amount' => 100, 'currency_id' => (string) $this->companyCurrency,
    ])->assertSuccessful();
    expect(DB::table('taxes')->where('expense_id', $id)->count())->toBe(1);

    putJson("/api/v1/expenses/{$id}", [
        'expense_date' => '2026-02-02', 'expense_category_id' => $this->categoryId,
        'amount' => 100, 'currency_id' => (string) $this->companyCurrency, 'taxes' => [],
    ])->assertSuccessful();
    expect(DB::table('taxes')->where('expense_id', $id)->count())->toBe(0);
});

it('attaches, replaces and serves a single receipt through the base64 endpoint', function () {
    $id = postJson('/api/v1/expenses', [
        'expense_date' => '2026-02-03', 'expense_category_id' => $this->categoryId,
        'amount' => 100, 'currency_id' => (string) $this->companyCurrency,
    ])->json('data.id');

    getJson("/api/v1/expenses/{$id}/show/receipt")->assertStatus(422)
        ->assertJson(['error' => 'receipt_does_not_exist']);

    $png = 'data:image/png;base64,'.base64_encode(base64_decode(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='));
    postJson("/api/v1/expenses/{$id}/upload/receipts", [
        'attachment_receipt' => json_encode(['name' => 'r1.png', 'data' => $png]), 'type' => 'create',
    ])->assertOk();
    postJson("/api/v1/expenses/{$id}/upload/receipts", [
        'attachment_receipt' => json_encode(['name' => 'r2.png', 'data' => $png]), 'type' => 'edit',
    ])->assertOk();

    expect(DB::table('media')->where('collection_name', 'receipts')->count())->toBe(1);
    getJson("/api/v1/expenses/{$id}/show/receipt")->assertOk();
});

it('refuses to delete a category in use and allows duplicate expense numbers', function () {
    postJson('/api/v1/expenses', [
        'expense_date' => '2026-02-04', 'expense_category_id' => $this->categoryId,
        'amount' => 10, 'currency_id' => (string) $this->companyCurrency, 'expense_number' => 'EXP-1',
    ])->assertSuccessful();
    postJson('/api/v1/expenses', [
        'expense_date' => '2026-02-05', 'expense_category_id' => $this->categoryId,
        'amount' => 20, 'currency_id' => (string) $this->companyCurrency, 'expense_number' => 'EXP-1',
    ])->assertSuccessful();

    deleteJson("/api/v1/categories/{$this->categoryId}")
        ->assertStatus(422)->assertJson(['error' => 'expense_attached']);
});
