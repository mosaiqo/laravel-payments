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
        ->and($checkout->get())
        ->toMatchArray([
            'data' => [
                'attributes' => [
                    'url' => 'https://lemon.lemonsqueezy.com/checkout/buy/variant_123',
                ],
            ],
        ]);
});

it('can returns a new checkout attributes', function () {
    config()->set([
        'payments.provider' => LaravelPayments::PROVIDER_LEMON_SQUEEZY,
        'payments.providers.lemon-squeezy.store' => 'store_12345',
        'payments.providers.lemon-squeezy.api_key' => 'fake_key',
    ]);

    $checkout = new Checkout('store_24398', 'variant_123');

    Http::fake([
        'api.lemonsqueezy.com/v1/checkouts' => Http::response([
            'data' => [
                'attributes' => [
                    'preview' => [
                        'foo' => 'bar',
                    ],
                'url' => 'https://lemon.lemonsqueezy.com/checkout/buy/variant_123']
            ],
        ]),
    ]);

    expect($checkout)
        ->toBeInstanceOf(Checkout::class)
        ->and($checkout->attributes())
        ->toMatchArray([
            'preview' => [
                'foo' => 'bar',
            ],
            'url' => 'https://lemon.lemonsqueezy.com/checkout/buy/variant_123',
        ]);
});

it('can returns a new checkout preview', function () {
    config()->set([
        'payments.provider' => LaravelPayments::PROVIDER_LEMON_SQUEEZY,
        'payments.providers.lemon-squeezy.store' => 'store_12345',
        'payments.providers.lemon-squeezy.api_key' => 'fake_key',
    ]);

    $checkout = new Checkout('store_24398', 'variant_123');

    Http::fake([
        'api.lemonsqueezy.com/v1/checkouts' => Http::response([
            'data' => ['attributes' => [
                'preview' => [
                    'foo' => 'bar',
                ],
                'url' => 'https://lemon.lemonsqueezy.com/checkout/buy/variant_123']
            ],
        ]),
    ]);

    expect($checkout)
        ->toBeInstanceOf(Checkout::class)
        ->and($checkout->preview())
        ->toMatchArray([
            'foo' => 'bar'
        ]);
});

it('can returns a new checkout url', function () {
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
        ->and($checkout->url())
        ->toBe('https://lemon.lemonsqueezy.com/checkout/buy/variant_123');
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
        ->withoutPreview()
        ->withoutLogo()
        ->withoutMedia()
        ->withoutDescription()
        ->withoutDiscountField();

    Http::fake([
        'api.lemonsqueezy.com/v1/checkouts' => function (Request $request) {
            expect(collect($request->data())->pluck('attributes')->first())
                ->toMatchArray([
                    "preview" => false,
                    'checkout_options' => [
                        'logo' => false,
                        'embed' => false,
                        'media' => false,
                        'desc' => false,
                        'discount' => false,
                        'dark' => false,
                        'subscription_preview' => true
                    ],
                ]);

            return Http::response([
                'data' => ['attributes' => ['url' => 'https://lemon.lemonsqueezy.com/checkout/buy/variant_123']],
            ]);
        },
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

it('can limit to specific variants', function () {
    config()->set([
        'payments.provider' => LaravelPayments::PROVIDER_LEMON_SQUEEZY,
        'payments.providers.lemon-squeezy.store' => 'store_12345',
        'payments.providers.lemon-squeezy.api_key' => 'fake_key',
    ]);
    $checkout = Checkout::make('store_24398', 'variant_123')
        ->withEnabledVariants([
            'var_789',
            'var_456'
        ]);

    Http::fake([
        'api.lemonsqueezy.com/v1/checkouts' => function (Request $request) {
            expect(collect($request->data())->pluck('attributes')->first())
                ->toMatchArray([
                    'product_options' => [
                        'enabled_variants' => ['var_789', 'var_456']
                    ],
                ]);

            return Http::response([
                'data' => ['attributes' => ['url' => 'https://lemon.lemonsqueezy.com/checkout/buy/variant_123']],
            ]);
        },
    ]);

    expect($checkout->url())
        ->toBe('https://lemon.lemonsqueezy.com/checkout/buy/variant_123');
});

it('can disable variants', function () {
    config()->set([
        'payments.provider' => LaravelPayments::PROVIDER_LEMON_SQUEEZY,
        'payments.providers.lemon-squeezy.store' => 'store_12345',
        'payments.providers.lemon-squeezy.api_key' => 'fake_key',
    ]);
    $checkout = Checkout::make('store_24398', 'variant_123')
        ->withoutVariants();

    Http::fake([
        'api.lemonsqueezy.com/v1/checkouts' => function (Request $request) {
            expect(collect($request->data())->pluck('attributes')->first())
                ->toMatchArray([
                    'product_options' => [
                        'enabled_variants' => ['variant_123']
                    ],
                ]);

            return Http::response([
                'data' => ['attributes' => ['url' => 'https://lemon.lemonsqueezy.com/checkout/buy/variant_123']],
            ]);
        },
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

it('can be used as a response', function () {
    config()->set([
        'payments.provider' => LaravelPayments::PROVIDER_LEMON_SQUEEZY,
        'payments.providers.lemon-squeezy.store' => 'store_12345',
        'payments.providers.lemon-squeezy.api_key' => 'fake_key',
    ]);

    Http::fake([
        'api.lemonsqueezy.com/v1/checkouts' => Http::response([
            'data' => ['attributes' => ['url' => 'https://lemon.lemonsqueezy.com/checkout/buy/variant_123']],
        ]),
    ]);

    $checkout = Checkout::make('store_24398', 'variant_123');

    expect($checkout->toResponse('fake_request'))
        ->toBeInstanceOf(RedirectResponse::class);
});
