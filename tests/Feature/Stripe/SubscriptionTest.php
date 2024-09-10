<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Mosaiqo\LaravelPayments\LaravelPayments;
use Mosaiqo\LaravelPayments\Models\Subscription;
use Tests\Traits\StripeTestHelpers;

uses(RefreshDatabase::class, StripeTestHelpers::class);

beforeAll(function () {
    static::$productId = static::stripe()->products->create([
        'name' => 'Laravel Payments Test Product',
        'type' => 'service',
    ])->id;

    static::$priceId = self::stripe()->prices->create([
        'product' => static::$productId,
        'nickname' => 'Monthly $10',
        'currency' => 'USD',
        'recurring' => [
            'interval' => 'month',
        ],
        'billing_scheme' => 'per_unit',
        'unit_amount' => 1000,
    ])->id;

    static::$otherPriceId = self::stripe()->prices->create([
        'product' => static::$productId,
        'nickname' => 'Monthly $10 Other',
        'currency' => 'USD',
        'recurring' => [
            'interval' => 'month',
        ],
        'billing_scheme' => 'per_unit',
        'unit_amount' => 1000,
    ])->id;

    static::$premiumPriceId = self::stripe()->prices->create([
        'product' => static::$productId,
        'nickname' => 'Monthly $20 Premium',
        'currency' => 'USD',
        'recurring' => [
            'interval' => 'month',
        ],
        'billing_scheme' => 'per_unit',
        'unit_amount' => 2000,
    ])->id;

    static::$couponId = self::stripe()->coupons->create([
        'duration' => 'repeating',
        'amount_off' => 500,
        'duration_in_months' => 3,
        'currency' => 'USD',
    ])->id;

    static::$taxRateId = self::stripe()->taxRates->create([
        'display_name' => 'VAT',
        'description' => 'VAT Spain',
        'jurisdiction' => 'ES',
        'percentage' => 21,
        'inclusive' => false,
    ])->id;
});
describe('Provider: Stripe', function () {
    it('can create a subscription', function () {
//        $user = $this->createCustomer('create_subscription');
//        $user->newSubscription('default', static::$priceId)->create();
        config()->set([
            'payments.provider' => LaravelPayments::PROVIDER_LEMON_SQUEEZY,
            'payments.providers.lemon-squeezy.store' => 'store_12345',
            'payments.providers.lemon-squeezy.api_key' => 'fake_key',
        ]);

        $subscription = Subscription::factory()->create([
            'provider_id' => '12345',
            'status' => Subscription::STATUS_ACTIVE,
        ]);

        Http::fake([
            'api.lemonsqueezy.com/*' => function (\Illuminate\Http\Client\Request $request) use ($subscription) {
                expect($request->url())
                    ->toBe('https://api.lemonsqueezy.com/v1/subscriptions/12345')
                    ->and($request->method())
                    ->toBe('PATCH')
                    ->and(collect($request->data())->pluck('attributes')->first())->toMatchArray([
                        'billing_anchor' => 12,
                    ]);

                return Http::response([
                    'data' => [
                        'attributes' => array_merge($subscription->toArray(), [
                            'status' => Subscription::STATUS_ACTIVE,
                        ]),
                    ],
                ]);
            },
        ]);

        $subscription = $subscription->anchorBillingCycleOn(12);
        expect($subscription)->toMatchArray(['provider_id' => '12345', 'status' => Subscription::STATUS_ACTIVE]);
    });

    it('can end a trial', function () {
        config()->set([
            'payments.provider' => LaravelPayments::PROVIDER_LEMON_SQUEEZY,
            'payments.providers.lemon-squeezy.store' => 'store_12345',
            'payments.providers.lemon-squeezy.api_key' => 'fake_key',
        ]);

        $subscription = Subscription::factory()->create([
            'provider_id' => '12345',
            'status' => Subscription::STATUS_ACTIVE,
        ]);

        Http::fake([
            'api.lemonsqueezy.com/*' => function (\Illuminate\Http\Client\Request $request) use ($subscription) {

                expect($request->url())
                    ->toBe('https://api.lemonsqueezy.com/v1/subscriptions/12345')
                    ->and($request->method())
                    ->toBe('PATCH')
                    ->and(collect($request->data())->pluck('attributes')->first())->toMatchArray([
                        'billing_anchor' => 0,
                    ]);

                return Http::response([
                    'data' => [
                        'attributes' => array_merge($subscription->toArray(), [
                            'status' => Subscription::STATUS_ACTIVE,
                        ]),
                    ],
                ]);
            },
        ]);

        $subscription = $subscription->endTrial();
        expect($subscription)->toMatchArray(['provider_id' => '12345', 'status' => Subscription::STATUS_ACTIVE]);
    });

    it('can swap a subscription', function () {
        config()->set([
            'payments.provider' => LaravelPayments::PROVIDER_LEMON_SQUEEZY,
            'payments.providers.lemon-squeezy.store' => 'store_12345',
            'payments.providers.lemon-squeezy.api_key' => 'fake_key',
        ]);

        $subscription = Subscription::factory()->create();

        Http::fake([
            'api.lemonsqueezy.com/*' => function (\Illuminate\Http\Client\Request $request) {
                expect(collect($request->data())->pluck('attributes')->first())->toMatchArray([
                    'product_id' => '12345',
                    'variant_id' => '67890',
                    'disable_prorations' => false,
                ]);

                return Http::response([
                    'data' => [
                        'attributes' => [
                            'status' => Subscription::STATUS_ACTIVE,
                            'product_id' => '12345',
                            'variant_id' => '67890',
                        ],
                    ],
                ]);
            },
        ]);
        $subscription = $subscription->swap('12345', '67890');
        expect($subscription)->toMatchArray(['product_id' => '12345', 'variant_id' => '67890']);
    });

    it('avoids prorating on subscription swap', function () {
        config()->set([
            'payments.provider' => LaravelPayments::PROVIDER_LEMON_SQUEEZY,
            'payments.providers.lemon-squeezy.store' => 'store_12345',
            'payments.providers.lemon-squeezy.api_key' => 'fake_key',
        ]);
        $subscription = Subscription::factory()->create();
        Http::fake([
            'api.lemonsqueezy.com/*' => function (\Illuminate\Http\Client\Request $request) {
//            dd($request->data());
                expect(collect($request->data())->pluck('attributes')->first())
                    ->toMatchArray([
                        'product_id' => '12345',
                        'variant_id' => '67890',
                        'disable_prorations' => true,
                    ]);

                return Http::response([
                    'data' => [
                        'attributes' => [
                            'status' => Subscription::STATUS_ACTIVE,
                            'product_id' => '12345',
                            'variant_id' => '67890',
                        ],
                    ],
                ]);
            },
        ]);

        $subscription = $subscription->noProrate()->swap('12345', '67890');
        expect($subscription)->toMatchArray(['product_id' => '12345', 'variant_id' => '67890']);
    });

    it('forces prorating on subscription swap', function () {
        config()->set([
            'payments.provider' => LaravelPayments::PROVIDER_LEMON_SQUEEZY,
            'payments.providers.lemon-squeezy.store' => 'store_12345',
            'payments.providers.lemon-squeezy.api_key' => 'fake_key',
        ]);
        $subscription = Subscription::factory()->create();
        Http::fake([
            'api.lemonsqueezy.com/*' => function (\Illuminate\Http\Client\Request $request) {
//            dd($request->data());
                expect(collect($request->data())->pluck('attributes')->first())
                    ->toMatchArray([
                        'product_id' => '12345',
                        'variant_id' => '67890',
                        'disable_prorations' => true,
                    ]);

                return Http::response([
                    'data' => [
                        'attributes' => [
                            'status' => Subscription::STATUS_ACTIVE,
                            'product_id' => '12345',
                            'variant_id' => '67890',
                        ],
                    ],
                ]);
            },
        ]);

        $subscription = $subscription->prorate()->setProration(false)->swap('12345', '67890');
        expect($subscription)->toMatchArray(['product_id' => '12345', 'variant_id' => '67890']);
    });

    it('can swap a subscription with instant invoice', function () {
        config()->set([
            'payments.provider' => LaravelPayments::PROVIDER_LEMON_SQUEEZY,
            'payments.providers.lemon-squeezy.store' => 'store_12345',
            'payments.providers.lemon-squeezy.api_key' => 'fake_key',
        ]);

        $subscription = Subscription::factory()->create();

        Http::fake([
            'api.lemonsqueezy.com/*' => function (\Illuminate\Http\Client\Request $request) {
                expect(collect($request->data())->pluck('attributes')->first())->toMatchArray([
                    'product_id' => '12345',
                    'variant_id' => '67890',
                    'invoice_immediately' => true,
                ]);

                return Http::response([
                    'data' => [
                        'attributes' => [
                            'status' => Subscription::STATUS_ACTIVE,
                            'product_id' => '12345',
                            'variant_id' => '67890',
                        ],
                    ],
                ]);
            },
        ]);
        $subscription = $subscription->swapAndInvoice('12345', '67890');

        expect($subscription)->toMatchArray(['product_id' => '12345', 'variant_id' => '67890']);
    });

    it('can cancel a subscription', function () {
        config()->set([
            'payments.provider' => LaravelPayments::PROVIDER_LEMON_SQUEEZY,
            'payments.providers.lemon-squeezy.store' => 'store_12345',
            'payments.providers.lemon-squeezy.api_key' => 'fake_key',
        ]);


        $subscription = Subscription::factory()->create([
            'provider_id' => '12345',
            'status' => Subscription::STATUS_ACTIVE,
        ]);


        Http::fake([
            'api.lemonsqueezy.com/*' => function (\Illuminate\Http\Client\Request $request) use ($subscription) {

                expect($request->url())
                    ->toBe('https://api.lemonsqueezy.com/v1/subscriptions/12345')
                    ->and($request->method())
                    ->toBe('DELETE');

                return Http::response([
                    'data' => [
                        'attributes' => array_merge($subscription->toArray(), [
                            'status' => Subscription::STATUS_CANCELED,
                        ]),
                    ],
                ]);
            },
        ]);

        $subscription = $subscription->cancel();
        expect($subscription)->toMatchArray(['provider_id' => '12345', 'status' => Subscription::STATUS_CANCELED]);
    });

    it('can resume a subscription', function () {
        config()->set([
            'payments.provider' => LaravelPayments::PROVIDER_LEMON_SQUEEZY,
            'payments.providers.lemon-squeezy.store' => 'store_12345',
            'payments.providers.lemon-squeezy.api_key' => 'fake_key',
        ]);

        $subscription = Subscription::factory()->create([
            'provider_id' => '12345',
            'status' => Subscription::STATUS_CANCELED,
        ]);

        Http::fake([
            'api.lemonsqueezy.com/*' => function (\Illuminate\Http\Client\Request $request) use ($subscription) {

                expect($request->url())
                    ->toBe('https://api.lemonsqueezy.com/v1/subscriptions/12345')
                    ->and($request->method())
                    ->toBe('PATCH');

                return Http::response([
                    'data' => [
                        'attributes' => array_merge($subscription->toArray(), [
                            'status' => Subscription::STATUS_ACTIVE,
                        ]),
                    ],
                ]);
            },
        ]);

        $subscription = $subscription->resume();
        expect($subscription)->toMatchArray(['provider_id' => '12345', 'status' => Subscription::STATUS_ACTIVE]);
    });

    it('can\'t resume a subscription if expired', function () {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Cannot resume an expired subscription.');
        config()->set([
            'payments.provider' => LaravelPayments::PROVIDER_LEMON_SQUEEZY,
            'payments.providers.lemon-squeezy.store' => 'store_12345',
            'payments.providers.lemon-squeezy.api_key' => 'fake_key',
        ]);

        $subscription = Subscription::factory()->create([
            'provider_id' => '12345',
            'status' => Subscription::STATUS_EXPIRED,
        ]);

        $subscription = $subscription->resume();
        expect($subscription)->toMatchArray(['provider_id' => '12345', 'status' => Subscription::STATUS_ACTIVE]);

    });

    it('can pause a subscription', function () {
        config()->set([
            'payments.provider' => LaravelPayments::PROVIDER_LEMON_SQUEEZY,
            'payments.providers.lemon-squeezy.store' => 'store_12345',
            'payments.providers.lemon-squeezy.api_key' => 'fake_key',
        ]);

        $subscription = Subscription::factory()->create([
            'provider_id' => '12345',
            'status' => Subscription::STATUS_ACTIVE,
        ]);

        Http::fake([
            'api.lemonsqueezy.com/*' => function (\Illuminate\Http\Client\Request $request) use ($subscription) {

                expect($request->url())
                    ->toBe('https://api.lemonsqueezy.com/v1/subscriptions/12345')
                    ->and($request->method())
                    ->toBe('PATCH')
                    ->and(collect($request->data())->pluck('attributes')->first())->toMatchArray([
                        'pause' => ['mode' => 'void', 'resumes_at' => null],
                    ]);

                return Http::response([
                    'data' => [
                        'attributes' => array_merge($subscription->toArray(), [
                            'status' => Subscription::STATUS_PAUSED,
                        ]),
                    ],
                ]);
            },
        ]);

        $subscription = $subscription->pause();
        expect($subscription)->toMatchArray(['provider_id' => '12345', 'status' => Subscription::STATUS_PAUSED]);
    });

    it('can pause a subscription for free', function () {
        config()->set([
            'payments.provider' => LaravelPayments::PROVIDER_LEMON_SQUEEZY,
            'payments.providers.lemon-squeezy.store' => 'store_12345',
            'payments.providers.lemon-squeezy.api_key' => 'fake_key',
        ]);

        $subscription = Subscription::factory()->create([
            'provider_id' => '12345',
            'status' => Subscription::STATUS_ACTIVE,
        ]);

        Http::fake([
            'api.lemonsqueezy.com/*' => function (\Illuminate\Http\Client\Request $request) use ($subscription) {

                expect($request->url())
                    ->toBe('https://api.lemonsqueezy.com/v1/subscriptions/12345')
                    ->and($request->method())
                    ->toBe('PATCH')
                    ->and(collect($request->data())->pluck('attributes')->first())->toMatchArray([
                        'pause' => ['mode' => 'free', 'resumes_at' => null],
                    ]);

                return Http::response([
                    'data' => [
                        'attributes' => array_merge($subscription->toArray(), [
                            'status' => Subscription::STATUS_PAUSED,
                        ]),
                    ],
                ]);
            },
        ]);

        $subscription = $subscription->pauseforFree();
        expect($subscription)->toMatchArray(['provider_id' => '12345', 'status' => Subscription::STATUS_PAUSED]);
    });

    it('can unpause a subscription', function () {
        config()->set([
            'payments.provider' => LaravelPayments::PROVIDER_LEMON_SQUEEZY,
            'payments.providers.lemon-squeezy.store' => 'store_12345',
            'payments.providers.lemon-squeezy.api_key' => 'fake_key',
        ]);

        $subscription = Subscription::factory()->create([
            'provider_id' => '12345',
            'status' => Subscription::STATUS_PAUSED,
        ]);

        Http::fake([
            'api.lemonsqueezy.com/*' => function (\Illuminate\Http\Client\Request $request) use ($subscription) {

                expect($request->url())
                    ->toBe('https://api.lemonsqueezy.com/v1/subscriptions/12345')
                    ->and($request->method())
                    ->toBe('PATCH')
                    ->and(collect($request->data())->pluck('attributes')->first())->toMatchArray([
                        'pause' => null,
                    ]);

                return Http::response([
                    'data' => [
                        'attributes' => array_merge($subscription->toArray(), [
                            'status' => Subscription::STATUS_ACTIVE,
                        ]),
                    ],
                ]);
            },
        ]);

        $subscription = $subscription->unpause();
        expect($subscription)->toMatchArray(['provider_id' => '12345', 'status' => Subscription::STATUS_ACTIVE]);
    });

    it('can update payment method url a subscription', function () {
        config()->set([
            'payments.provider' => LaravelPayments::PROVIDER_LEMON_SQUEEZY,
            'payments.providers.lemon-squeezy.store' => 'store_12345',
            'payments.providers.lemon-squeezy.api_key' => 'fake_key',
        ]);

        $subscription = Subscription::factory()->create([
            'provider_id' => '12345',
            'status' => Subscription::STATUS_ACTIVE,
        ]);

        Http::fake([
            'api.lemonsqueezy.com/*' => function (\Illuminate\Http\Client\Request $request) use ($subscription) {

                expect($request->url())
                    ->toBe('https://api.lemonsqueezy.com/v1/subscriptions/12345')
                    ->and($request->method())
                    ->toBe('GET');

                return Http::response([
                    'data' => [
                        'attributes' => array_merge($subscription->toArray(), [
                            'urls' => ['update_payment_method' => 'https://lemon.lemonsqueezy.com/checkout/buy/variant_123'],
                        ]),
                    ],
                ]);
            },
        ]);

        $url = $subscription->updatePaymentMethodUrl();
        expect($url)->toBe('https://lemon.lemonsqueezy.com/checkout/buy/variant_123');
    });

    it('can returns only correct when scoped', function () {
        config()->set([
            'payments.provider' => LaravelPayments::PROVIDER_LEMON_SQUEEZY,
            'payments.providers.lemon-squeezy.store' => 'store_12345',
            'payments.providers.lemon-squeezy.api_key' => 'fake_key',
        ]);
        Subscription::factory()->createMany([
            ['status' => Subscription::STATUS_ON_TRIAL],
            ['status' => Subscription::STATUS_ACTIVE],
            ['status' => Subscription::STATUS_PAUSED],
            ['status' => Subscription::STATUS_PAST_DUE],
            ['status' => Subscription::STATUS_UNPAID],
            ['status' => Subscription::STATUS_CANCELED],
            ['status' => Subscription::STATUS_EXPIRED],
        ]);

        $items = Subscription::query()->onTrial()->get();
        expect($items->count())->toBe(1);
        $items->each(function ($subscription) {
            expect($subscription->status)->toBe(Subscription::STATUS_ON_TRIAL);
        });

        $items = Subscription::query()->active()->get();
        expect($items->count())->toBe(1);
        $items->each(function ($subscription) {
            expect($subscription->status)->toBe(Subscription::STATUS_ACTIVE);
        });

        $items = Subscription::query()->paused()->get();
        expect($items->count())->toBe(1);
        $items->each(function ($subscription) {
            expect($subscription->status)->toBe(Subscription::STATUS_PAUSED);
        });

        $items = Subscription::query()->pastDue()->get();
        expect($items->count())->toBe(1);
        $items->each(function ($subscription) {
            expect($subscription->status)->toBe(Subscription::STATUS_PAST_DUE);
        });

        $items = Subscription::query()->unpaid()->get();
        expect($items->count())->toBe(1);
        $items->each(function ($subscription) {
            expect($subscription->status)->toBe(Subscription::STATUS_UNPAID);
        });

        $items = Subscription::query()->canceled()->get();
        expect($items->count())->toBe(1);
        $items->each(function ($subscription) {
            expect($subscription->status)->toBe(Subscription::STATUS_CANCELED);
        });

        $items = Subscription::query()->expired()->get();
        expect($items->count())->toBe(1);
        $items->each(function ($subscription) {
            expect($subscription->status)->toBe(Subscription::STATUS_EXPIRED);
        });
    });

    it('can determine if the subscription is within grace period', function () {
        config()->set([
            'payments.provider' => LaravelPayments::PROVIDER_LEMON_SQUEEZY,
            'payments.providers.lemon-squeezy.store' => 'store_12345',
            'payments.providers.lemon-squeezy.api_key' => 'fake_key',
        ]);
        $subscription = Subscription::factory()->create([
            'status' => Subscription::STATUS_CANCELED,
            'ends_at' => now()->add(1, 'day'),
        ]);

        expect($subscription->onGracePeriod())->toBeTrue();
    });

    it('can determine if the subscription is on paused period', function () {
        config()->set([
            'payments.provider' => LaravelPayments::PROVIDER_LEMON_SQUEEZY,
            'payments.providers.lemon-squeezy.store' => 'store_12345',
            'payments.providers.lemon-squeezy.api_key' => 'fake_key',
        ]);
        $subscription = Subscription::factory()->create([
            'status' => Subscription::STATUS_PAUSED,
            'pause_resumes_at' => now()->add(1, 'day'),
        ]);

        expect($subscription->onPausedPeriod())->toBeTrue();
    });
})->skip(true
    //&& !getenv('STRIPE_API_KEY')
);
