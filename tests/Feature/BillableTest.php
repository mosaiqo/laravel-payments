<?php

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Mosaiqo\LaravelPayments\LaravelPayments;
use Mosaiqo\LaravelPayments\Models\Customer;
use Tests\Fixtures\User;

it('can generate a checkout for a billable', function () {
    config()->set([
        'payments.provider' => LaravelPayments::PROVIDER_LEMON_SQUEEZY,
        'payments.providers.lemon-squeezy.store' => 'store_12345',
    ]);

    Http::fake([
        'api.lemonsqueezy.com/v1/checkouts' => Http::response([
            'data' => ['attributes' => ['url' => 'https://lemon.lemonsqueezy.com/checkout/buy/variant_1234']],
        ]),
    ]);

    $checkout = (new User)->checkout('variant_1234');

    expect($checkout->url())
        ->toBe('https://lemon.lemonsqueezy.com/checkout/buy/variant_1234');
});


it('can generate a checkout for a billable with custom data', function () {
    config()->set([
        'payments.provider' => LaravelPayments::PROVIDER_LEMON_SQUEEZY,
        'payments.providers.lemon-squeezy.store' => 'store_12345',
    ]);
    $user = (new User);
    Http::fake([
        'api.lemonsqueezy.com/v1/checkouts' => function (Request $request) use ($user) {
            expect(collect($request->data())->pluck('attributes')->first())
//                ->dd()
                ->toMatchArray([
                    'checkout_options' => [
                        'logo' => true,
                        'embed' => false,
                        'media' => true,
                        'desc' => true,
                        'discount' => true,
                        'dark' => false,
                        'subscription_preview' => true,
                    ],
                    'checkout_data' => [
                        'name' => 'Boudy de Geer',
                        'email' => 'boudydegeer@mosaiqo.com',
                        'billing_address' => [
                            'country' => 'NL',
                            'zip' => '1234AB',
                        ],
                        'tax_number' => 'NL123456789B01',
                        'discount_code' => '10PERCENTOFF',
                        'custom' => [
                            'batch_id' => '789',
                            'billable_id' => 'user_123',
                            'billable_type' => $user->getMorphClass(),
                        ],
                    ],
                ]);

            return Http::response([
                'data' => ['attributes' => ['url' => 'https://lemon.lemonsqueezy.com/checkout/buy/variant_123']],
            ]);
        },
    ]);

    $checkout = $user->checkout('variant_123', [
        'name' => 'Boudy de Geer',
        'email' => 'boudydegeer@mosaiqo.com',
        'country' => 'NL',
        'zip' => '1234AB',
        'tax_number' => 'NL123456789B01',
        'discount_code' => '10PERCENTOFF',
        'custom_price' => '1234',
    ], [
        'batch_id' => '789',
    ]);

    expect($checkout->url())
        ->toBe('https://lemon.lemonsqueezy.com/checkout/buy/variant_123');
});

it('can generate a checkout for a billable with specifics', function () {
    config()->set([
        'payments.provider' => LaravelPayments::PROVIDER_LEMON_SQUEEZY,
        'payments.providers.lemon-squeezy.store' => 'store_12345',
    ]);
    $user = (new User);
    Http::fake([
        'api.lemonsqueezy.com/v1/checkouts' => function (Request $request) use ($user) {
            expect(collect($request->data())->pluck('attributes')->first())
                ->toMatchArray([
                    'custom_price' => 1234,
                    'checkout_options' => [
                        'logo' => true,
                        'embed' => false,
                        'media' => true,
                        'desc' => true,
                        'discount' => true,
                        'dark' => true,
                        'subscription_preview' => false,
                        'button_color' => '#ffffff',
                    ],
                    "product_options" => [
                        "name" => "My Product",
                        "description" => "My Product Description",
                        "receipt_thank_you_note" => "Thank you for your purchase",
                        "redirect_url" => "https://example.com",
                    ],
                    "expires_at" => now()->addDay()->format(DateTimeInterface::ATOM),
                    'checkout_data' => [
                        'billing_address' => [],
                        'custom' => [
                            'billable_id' => 'user_123',
                            'billable_type' => $user->getMorphClass(),
                        ],
                    ],
                ]);

            return Http::response([
                'data' => ['attributes' => ['url' => 'https://lemon.lemonsqueezy.com/checkout/buy/variant_123']],
            ]);
        },
    ]);

    $checkout = $user->checkout('variant_123')
        ->dark()
        ->withCustomPrice(1234)
        ->withProductName('My Product')
        ->withDescription('My Product Description')
        ->withThankYouNote('Thank you for your purchase')
        ->withoutSubscriptionPreview()
        ->withButtonColor("#ffffff")
        ->redirectTo('https://example.com')
        ->expiresAt(now()->addDay());


    expect($checkout->url())
        ->toBe('https://lemon.lemonsqueezy.com/checkout/buy/variant_123');
});

it('can not overwrite the customer id and type or subscription id for a billable', function () {
    config()->set([
        'payments.provider' => LaravelPayments::PROVIDER_LEMON_SQUEEZY,
        'payments.providers.lemon-squeezy.store' => 'store_12345',
    ]);

    $this->expectExceptionMessage(
        'You cannot use "billable_id", "billable_type" or "subscription_type" as custom data keys because these are reserved keywords.'
    );

    $checkout = (new User)->checkout('variant_123')
        ->withCustomData([
            'billable_id' => '567',
            'billable_type' => 'App\\Models\\User',
        ]);
});

it('needs a configured store to generate checkouts', function () {
    config()->set([
        'payments.provider' => LaravelPayments::PROVIDER_LEMON_SQUEEZY,
        'payments.providers.lemon-squeezy.store' => null,
    ]);
    $this->expectExceptionMessage('The Lemon Squeezy store was not configured.');

    (new User)->checkout('variant_123');
});

it('can generate a customer portal link for a billable', function () {
    config()->set([
        'payments.provider' => LaravelPayments::PROVIDER_LEMON_SQUEEZY,
        'payments.providers.lemon-squeezy.store' => null,
        'payments.providers.lemon-squeezy.api_key' => 'fake_key',
    ]);

    Http::fake([
        'api.lemonsqueezy.com/v1/customers/1' => function (Request $request) {
            return Http::response([
                'data' => [
                    'attributes' => [
                        'urls' => [
                            'customer_portal' => 'https://my-store.lemonsqueezy.com/billing?expires=1666869343&signature=xxxxx',
                        ],
                    ],
                ],
            ]);
        },
    ]);

    $user = new User;
    $user->customer = (object)['provider_id' => 1];
    $url = $user->customerPortalUrl();

    expect($url)
        ->toBe('https://my-store.lemonsqueezy.com/billing?expires=1666869343&signature=xxxxx');
});


it('needs a configured provider create a customer', function () {
    config()->set(['payments.provider' => null]);

    $this->expectExceptionMessage('No payments provider was configured.');

    $user = User::factory()->create();
    $customer = $user->createAsCustomer();
});

it('only allowed providers can be configured', function () {
    config()->set(['payments.provider' => 'other']);

    $this->expectExceptionMessage('The payment provider [other] is not supported.');

    $user = User::factory()->create();
    $customer = $user->createAsCustomer();
});


it('can determine the generic trial on a billable', function () {
    config()->set(['payments.provider' => LaravelPayments::PROVIDER_LEMON_SQUEEZY]);

    $user = User::factory()->create();
    $customer = $user->createAsCustomer();
    expect($customer)->toBeInstanceOf(Customer::class);
});




