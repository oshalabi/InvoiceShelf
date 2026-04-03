<?php

use App\Models\CompanySetting;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\post;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);

    $user = User::find(1);
    $companyId = $user->companies()->first()->id;
    $this->companyId = $companyId;

    $this->withHeaders([
        'company' => $companyId,
        'Accept' => 'application/json',
    ]);

    Sanctum::actingAs($user, ['*']);

    CompanySetting::setSettings([
        'ocr_expense_enabled' => 'YES',
        'ocr_confidence_threshold' => 0.85,
        'ocr_country_code' => 'NL',
        'ocr_openrouter_enabled' => 'NO',
        'ocr_auto_generate_templates_enabled' => 'NO',
    ], $companyId);

    config()->set('services.ocr.base_url', 'https://ocr.test/api/expenses/preview');
    config()->set('services.ocr.api_key', 'test-key');
    config()->set('services.ocr.timeout', 5);
    config()->set('services.ocr.required_fields', 'invoice_number,date,amount,currency_code');
});

test('preview expense ocr maps supported high confidence fields', function () {
    Http::fake([
        'https://ocr.test/*' => Http::response([
            'status' => 'success',
            'fields' => [
                'invoice_date' => ['value' => '2026-04-01', 'confidence' => 0.99],
                'invoice_number' => ['value' => 'INV-2026-004', 'confidence' => 0.91],
                'total_amount' => ['value' => '123.45', 'confidence' => 0.96],
                'currency_code' => ['value' => 'EUR', 'confidence' => 0.95],
                'vat_amount' => ['value' => '21.00', 'confidence' => 0.90],
            ],
        ]),
    ]);

    $response = post('/api/v1/expenses/ocr-preview', [
        'receipt' => UploadedFile::fake()->create('invoice.pdf', 100, 'application/pdf'),
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('status', 'partial')
        ->assertJsonPath('mapped_fields.expense_date', '2026-04-01')
        ->assertJsonPath('mapped_fields.expense_number', 'INV-2026-004')
        ->assertJsonPath('mapped_fields.amount', 12345)
        ->assertJsonPath('message', 'Some expense details need review before submission.');

    expect($response->json('mapped_fields.currency_id'))->not->toBeNull();
    expect($response->json('unmapped_fields.vat_amount.reason'))->toBe('Field is not mapped to the expense form.');

    Http::assertSent(function ($request) {
        $multipartFields = collect($request->data())->keyBy('name');

        return $request->hasFile('file', filename: 'invoice.pdf')
            && $multipartFields->get('required_fields')['contents'] === 'invoice_number,date,amount,currency_code'
            && $multipartFields->get('openrouter_enabled')['contents'] === 'false'
            && $multipartFields->get('auto_generate_templates')['contents'] === 'false';
    });
});

test('preview expense ocr flags low confidence fields for review', function () {
    Http::fake([
        'https://ocr.test/*' => Http::response([
            'status' => 'success',
            'fields' => [
                'invoice_date' => ['value' => '01-04-2026', 'confidence' => 0.60],
                'invoice_number' => ['value' => 'INV-LOW-1', 'confidence' => 0.95],
                'total_amount' => ['value' => '88.90', 'confidence' => 0.40],
            ],
        ]),
    ]);

    $response = post('/api/v1/expenses/ocr-preview', [
        'receipt' => UploadedFile::fake()->create('invoice.png', 100, 'image/png'),
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('status', 'partial')
        ->assertJsonPath('mapped_fields.expense_number', 'INV-LOW-1')
        ->assertJsonPath('flagged_fields.expense_date.suggested_value', '2026-04-01')
        ->assertJsonPath('flagged_fields.expense_date.reason', 'Confidence below threshold.')
        ->assertJsonPath('flagged_fields.amount.suggested_value', 8890);
});

test('preview expense ocr validates supported file types', function () {
    $response = post('/api/v1/expenses/ocr-preview', [
        'receipt' => UploadedFile::fake()->create('invoice.gif', 100, 'image/gif'),
    ]);

    $response
        ->assertStatus(422)
        ->assertJsonValidationErrors(['receipt']);
});

test('preview expense ocr validates max upload size', function () {
    $response = post('/api/v1/expenses/ocr-preview', [
        'receipt' => UploadedFile::fake()->create('invoice.pdf', 11000, 'application/pdf'),
    ]);

    $response
        ->assertStatus(422)
        ->assertJsonValidationErrors(['receipt']);
});

test('preview expense ocr returns disabled status when company setting is off', function () {
    CompanySetting::setSettings([
        'ocr_expense_enabled' => 'NO',
    ], $this->companyId);

    Http::fake();

    $response = post('/api/v1/expenses/ocr-preview', [
        'receipt' => UploadedFile::fake()->create('invoice.pdf', 100, 'application/pdf'),
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('status', 'disabled')
        ->assertJsonPath('message', 'OCR autofill is disabled for this company.');

    Http::assertNothingSent();
});

test('preview expense ocr passes company required field overrides to the OCR service', function () {
    CompanySetting::setSettings([
        'ocr_required_fields' => 'invoice_number,date,amount',
        'ocr_openrouter_enabled' => 'YES',
        'ocr_auto_generate_templates_enabled' => 'YES',
    ], $this->companyId);

    Http::fake([
        'https://ocr.test/*' => Http::response([
            'status' => 'success',
            'fields' => [
                'invoice_date' => ['value' => '2026-04-01', 'confidence' => 0.99],
                'invoice_number' => ['value' => 'INV-2026-004', 'confidence' => 0.91],
                'total_amount' => ['value' => '123.45', 'confidence' => 0.96],
            ],
        ]),
    ]);

    post('/api/v1/expenses/ocr-preview', [
        'receipt' => UploadedFile::fake()->create('invoice.pdf', 100, 'application/pdf'),
    ])->assertOk();

    Http::assertSent(function ($request) {
        $multipartFields = collect($request->data())->keyBy('name');

        return $request->hasFile('file', filename: 'invoice.pdf')
            && $multipartFields->get('required_fields')['contents'] === 'invoice_number,date,amount'
            && $multipartFields->get('openrouter_enabled')['contents'] === 'true'
            && $multipartFields->get('auto_generate_templates')['contents'] === 'true';
    });
});

test('preview expense ocr returns failed status when required field configuration is invalid', function () {
    config()->set('services.ocr.required_fields', 'invoice_number,unknown_field');

    Http::fake();

    $response = post('/api/v1/expenses/ocr-preview', [
        'receipt' => UploadedFile::fake()->create('invoice.pdf', 100, 'application/pdf'),
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('status', 'failed')
        ->assertJsonPath('message', 'OCR required field configuration is invalid. Please review company OCR settings.');

    Http::assertNothingSent();
});

test('preview expense ocr returns failed status when provider is unavailable', function () {
    Http::fake(function () {
        throw new ConnectionException('Connection failed');
    });

    $response = post('/api/v1/expenses/ocr-preview', [
        'receipt' => UploadedFile::fake()->create('invoice.pdf', 100, 'application/pdf'),
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('status', 'failed')
        ->assertJsonPath('message', 'OCR service unavailable. Please try again later or enter the expense manually.');
});
