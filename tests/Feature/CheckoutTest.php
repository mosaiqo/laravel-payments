<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Http;
use Mosaiqo\LaravelPayments\Checkout;
use Mosaiqo\LaravelPayments\LaravelPayments;

uses(RefreshDatabase::class);
it('can initiate a new checkout', function () {
    config()->set([
        'payments.provider' => LaravelPayments::PROVIDER_LEMON_SQUEEZY,
        'payments.providers.lemon-squeezy.store' => 'store_12345',
        'payments.providers.lemon-squeezy.api_key' => 'fake_key',
    ]);

    $checkout = new Checkout('store_24398', 'variant_123');

    Http::fake([
        'api.lemonsqueezy.com/v1/checkouts' => Http::response([
            'data' => ['attributes' => ['url' => 'https://lemon.lemonsqueezy.com/checkout/buy/variant_123']],
        ]),
    ]);

    expect($checkout)
        ->toBeInstanceOf(Checkout::class)
        ->and($checkout->url())->toBe('https://lemon.lemonsqueezy.com/checkout/buy/variant_123');
});

it('can be redirected', function () {
    config()->set([
        'payments.provider' => LaravelPayments::PROVIDER_LEMON_SQUEEZY,
        'payments.providers.lemon-squeezy.store' => 'store_12345',
        'payments.providers.lemon-squeezy.api_key' => 'fake_key',
    ]);
    $checkout = new Checkout('store_24398', 'variant_123');

    Http::fake([
        'api.lemonsqueezy.com/v1/checkouts' => Http::response([
            'data' => ['attributes' => ['url' => 'https://lemon.lemonsqueezy.com/checkout/buy/variant_123']],
        ]),
    ]);

    expect($checkout->redirect())->toBeInstanceOf(RedirectResponse::class)
        ->and($checkout->redirect()->getTargetUrl())->toBe('https://lemon.lemonsqueezy.com/checkout/buy/variant_123');
});

it('can turn off toggles', function () {
    config()->set([
        'payments.provider' => LaravelPayments::PROVIDER_LEMON_SQUEEZY,
        'payments.providers.lemon-squeezy.store' => 'store_12345',
        'payments.providers.lemon-squeezy.api_key' => 'fake_key',
    ]);
    $checkout = Checkout::make('store_24398', 'variant_123')
        ->withoutLogo()
        ->withoutMedia()
        ->withoutDescription()
        ->withoutDiscountField();
    Http::fake([
        'api.lemonsqueezy.com/v1/checkouts' => Http::response([
            'data' => ['attributes' => ['url' => 'https://lemon.lemonsqueezy.com/checkout/buy/variant_123']],
        ]),
    ]);

    expect($checkout->url())->toBe('https://lemon.lemonsqueezy.com/checkout/buy/variant_123');

});

it('can set prefilled fields with dedicated methods', function () {
    config()->set([
        'payments.provider' => LaravelPayments::PROVIDER_LEMON_SQUEEZY,
        'payments.providers.lemon-squeezy.store' => 'store_12345',
        'payments.providers.lemon-squeezy.api_key' => 'fake_key',
    ]);
    $checkout = Checkout::make('store_24398', 'variant_123')
        ->withName('John Doe')
        ->withEmail('john@example.com')
        ->withBillingAddress('US', '10038')
        ->withTaxNumber('GB123456789')
        ->withDiscountCode('10PERCENTOFF');

    Http::fake([
        'api.lemonsqueezy.com/v1/checkouts' => Http::response([
            'data' => ['attributes' => ['url' => 'https://lemon.lemonsqueezy.com/checkout/buy/variant_123']],
        ]),
    ]);

    expect($checkout->url())
        ->toBe('https://lemon.lemonsqueezy.com/checkout/buy/variant_123');
});

it('can include custom data', function () {
    config()->set([
        'payments.provider' => LaravelPayments::PROVIDER_LEMON_SQUEEZY,
        'payments.providers.lemon-squeezy.store' => 'store_12345',
        'payments.providers.lemon-squeezy.api_key' => 'fake_key',
    ]);
    $checkout = Checkout::make('store_24398', 'variant_123')
        ->withCustomData([
            'order_id' => '789',
        ]);

    Http::fake([
        'api.lemonsqueezy.com/v1/checkouts' => Http::response([
            'data' => ['attributes' => ['url' => 'https://lemon.lemonsqueezy.com/checkout/buy/variant_123']],
        ]),
    ]);

    expect($checkout->url())
        ->toBe('https://lemon.lemonsqueezy.com/checkout/buy/variant_123');
});

it('can include prefilled fields and custom data', function () {
    config()->set([
        'payments.provider' => LaravelPayments::PROVIDER_LEMON_SQUEEZY,
        'payments.providers.lemon-squeezy.store' => 'store_12345',
        'payments.providers.lemon-squeezy.api_key' => 'fake_key',
    ]);
    $checkout = Checkout::make('store_24398', 'variant_123')
        ->withName('John Doe')
        ->withCustomData([
            'order_id' => '789',
        ]);

    Http::fake([
        'api.lemonsqueezy.com/v1/checkouts' => function (Request $request) {
            expect($request->data())
                ->toHaveKey("data.attributes.checkout_data.name", "John Doe")
                ->and($request->data())
                ->toHaveKey("data.attributes.checkout_data.custom.order_id", "789");

            return Http::response([
                'data' => ['attributes' => ['url' => 'https://lemon.lemonsqueezy.com/checkout/buy/variant_123']],
            ]);
        },
    ]);


    expect($checkout->url())
        ->toBe('https://lemon.lemonsqueezy.com/checkout/buy/variant_123');
});
