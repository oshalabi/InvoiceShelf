<?php

use App\Domains\Accounts\Models\User;
use App\Domains\Receivables\Models\Payment;
use App\Domains\Sales\Models\Estimate;
use App\Domains\Sales\Models\Invoice;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'RealisticDemoSeeder', '--force' => true]);
});

/**
 * The PDF routes bind on unique_hash (`/invoices/pdf/{invoice:unique_hash}`), and
 * the seeder builds its documents with Model::create() rather than through the
 * service layer that normally assigns it. When that was missed, every seeded
 * document produced a `/invoices/pdf/` URL with an empty segment — a 404, surfaced
 * in the UI only as "Unable to load document preview", with nothing in the log.
 *
 * Anything the seeder creates that the app then serves by hash has to carry one.
 */
test('every seeded document has the unique hash its pdf route binds on', function (string $model) {
    expect($model::count())->toBeGreaterThan(0);

    expect($model::whereNull('unique_hash')->orWhere('unique_hash', '')->count())
        ->toBe(0);
})->with([
    Invoice::class,
    Estimate::class,
    Payment::class,
]);

test('seeded documents maintain serial number sequences and next numbers', function () {
    $companyId = User::where('email', 'demo@invoiceshelf.com')
        ->firstOrFail()
        ->companies()
        ->firstOrFail()
        ->id;

    $documents = [
        [Invoice::class, ['type' => Invoice::TYPE_INVOICE], 35],
        [Estimate::class, [], 8],
        [Payment::class, [], 17],
    ];

    foreach ($documents as [$model, $scope, $expectedCount]) {
        $query = $model::query()->where('company_id', $companyId);

        foreach ($scope as $column => $value) {
            $query->where($column, $value);
        }

        $seededDocuments = $query->get();

        expect($seededDocuments)->toHaveCount($expectedCount)
            ->and($seededDocuments->whereNull('sequence_number'))->toBeEmpty()
            ->and($seededDocuments->whereNull('customer_sequence_number'))->toBeEmpty()
            ->and($seededDocuments->pluck('sequence_number')->sort()->values()->all())
            ->toBe(range(1, $expectedCount));

        $seededDocuments
            ->groupBy('customer_id')
            ->each(function ($customerDocuments): void {
                expect($customerDocuments->pluck('customer_sequence_number')->sort()->values()->all())
                    ->toBe(range(1, $customerDocuments->count()));
            });
    }

    $user = User::where('email', 'demo@invoiceshelf.com')->firstOrFail();
    Sanctum::actingAs($user, ['*']);
    $this->withHeaders(['company' => $companyId]);

    getJson('api/v1/next-number?key=invoice')
        ->assertOk()
        ->assertJson(['nextNumber' => 'INV-000036']);

    getJson('api/v1/next-number?key=estimate')
        ->assertOk()
        ->assertJson(['nextNumber' => 'EST-000009']);

    getJson('api/v1/next-number?key=payment')
        ->assertOk()
        ->assertJson(['nextNumber' => 'PAY-000018']);
});

test('rerunning the realistic demo seeder remains idempotent', function () {
    $companyId = User::where('email', 'demo@invoiceshelf.com')
        ->firstOrFail()
        ->companies()
        ->firstOrFail()
        ->id;

    Artisan::call('db:seed', ['--class' => 'RealisticDemoSeeder', '--force' => true]);

    expect(Invoice::where('company_id', $companyId)->where('type', Invoice::TYPE_INVOICE)->count())->toBe(35)
        ->and(Estimate::where('company_id', $companyId)->count())->toBe(8)
        ->and(Payment::where('company_id', $companyId)->count())->toBe(17);
});
