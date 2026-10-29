<?php

namespace Tests\Feature\Customer;

use App\Domains\Contacts\Http\Controllers\CustomerPortal\ProfileController;
use App\Domains\Contacts\Http\Requests\CustomerPortal\CustomerProfileRequest;
use App\Domains\Contacts\Models\Address;
use App\Domains\Contacts\Models\Customer;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'DemoSeeder', '--force' => true]);

    $customer = Customer::factory()->create();

    Sanctum::actingAs(
        $customer,
        ['*'],
        'customer'
    );
});

test('update customer profile using a form request', function () {
    $this->assertActionUsesFormRequest(
        ProfileController::class,
        'updateProfile',
        CustomerProfileRequest::class
    );
});

test('update customer profile', function () {
    $customer = Auth::guard('customer')->user();

    $newCustomer = Customer::factory()->raw([
        'shipping' => [
            'name' => 'newName',
            'address_street_1' => 'address',
        ],
        'billing' => [
            'name' => 'newName',
            'address_street_1' => 'address',
        ],
    ]);

    postJson("api/v1/{$customer->company->slug}/customer/profile", $newCustomer)->assertOk();

    $this->assertDatabaseHas('customers', [
        'name' => $customer['name'],
        'email' => $customer['email'],
    ]);
});

test('get customer', function () {
    $customer = Auth::guard('customer')->user();

    getJson("api/v1/{$customer->company->slug}/customer/me")->assertOk();
});

test('updating one address does not replace the other address', function () {
    $customer = Auth::guard('customer')->user();
    $billing = Address::factory()->create([
        'customer_id' => $customer->id,
        'type' => Address::BILLING_TYPE,
        'name' => 'Old Billing',
    ]);
    $shipping = Address::factory()->create([
        'customer_id' => $customer->id,
        'type' => Address::SHIPPING_TYPE,
        'name' => 'Existing Shipping',
    ]);

    postJson("api/v1/{$customer->company->slug}/customer/profile", [
        'billing' => [
            'name' => 'New Billing',
            'address_street_1' => 'Billing Street',
        ],
    ])->assertOk();

    expect($customer->fresh()->billingAddress->name)->toBe('New Billing')
        ->and($customer->fresh()->shippingAddress->is($shipping))->toBeTrue();

    $this->assertDatabaseMissing('addresses', ['id' => $billing->id]);
});
