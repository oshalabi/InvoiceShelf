<?php

use App\Domains\Receivables\Mail\SendPaymentMail;
use App\Domains\Receivables\Models\Payment;
use App\Platform\Mail\Models\EmailLog;
use App\Platform\Persistence\ModelIdentityMap;
use Illuminate\Support\Facades\Artisan;

use function Pest\Laravel\getJson;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);
});

test('payment mail records a stable public link through the mail platform', function () {
    $payment = Payment::factory()->create();
    $mail = new SendPaymentMail([
        'from' => 'billing@example.com',
        'to' => 'customer@example.com',
        'subject' => 'Payment receipt',
        'body' => 'Thanks for your payment.',
        'payment' => $payment->toArray(),
        'attach' => ['data' => null],
    ]);

    $mail->build();

    $log = EmailLog::query()->sole();

    expect($log->mailable_type)->toBe(ModelIdentityMap::aliasFor(Payment::class))
        ->and((int) $log->mailable_id)->toBe($payment->id)
        ->and($log->token)->not->toBeEmpty()
        ->and($mail->data['url'])->toBe(route('payment', ['email_log' => $log->token]));

    getJson('/customer/payments/'.$log->token)
        ->assertOk()
        ->assertJsonPath('data.id', $payment->id);
});
