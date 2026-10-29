<?php

use App\Domains\Purchases\Contracts\ExpenseReceiptManager;
use App\Domains\Purchases\Data\PendingExpenseReceipt;
use App\Domains\Purchases\Models\Expense;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);
});

test('it stores replaces reads and clears expense receipts through the purchases contract', function () {
    Storage::fake('local');

    $expense = Expense::factory()->create();
    $manager = app(ExpenseReceiptManager::class);

    $uploadedReceipt = UploadedFile::fake()->create('first-receipt.pdf', 10, 'application/pdf');

    $manager->attach($expense, new PendingExpenseReceipt(
        $uploadedReceipt->getPathname(),
        $uploadedReceipt->getClientOriginalName(),
    ));

    $firstReceipt = $manager->first($expense);

    expect($firstReceipt)->not->toBeNull()
        ->and($firstReceipt->fileName)->toBe('first-receipt.pdf')
        ->and($expense->fresh()->getMedia('receipts'))->toHaveCount(1);

    $manager->attachBase64(
        $expense,
        'data:image/png;base64,'.base64_encode('replacement receipt'),
        'replacement.png',
        replaceExisting: true,
    );

    $replacement = $manager->first($expense);

    expect($replacement)->not->toBeNull()
        ->and($replacement->fileName)->toBe('replacement.png')
        ->and($expense->fresh()->getMedia('receipts'))->toHaveCount(1);

    $manager->clear($expense);

    expect($manager->first($expense))->toBeNull()
        ->and($expense->fresh()->getMedia('receipts'))->toBeEmpty();
});
