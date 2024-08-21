<?php

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Event;
use Mosaiqo\LaravelPayments\Database\Factories\OrderFactory;
use Mosaiqo\LaravelPayments\Database\Factories\SubscriptionFactory;
use Mosaiqo\LaravelPayments\Events\LicenseKeyCreated;
use Mosaiqo\LaravelPayments\Events\LicenseKeyUpdated;
use Mosaiqo\LaravelPayments\Events\OrderCreated;
use Mosaiqo\LaravelPayments\Events\OrderRefunded;
use Mosaiqo\LaravelPayments\Events\SubscriptionCanceled;
use Mosaiqo\LaravelPayments\Events\SubscriptionCreated;
use Mosaiqo\LaravelPayments\Events\SubscriptionExpired;
use Mosaiqo\LaravelPayments\Events\SubscriptionPaused;
use Mosaiqo\LaravelPayments\Events\SubscriptionPaymentFailed;
use Mosaiqo\LaravelPayments\Events\SubscriptionPaymentRecovered;
use Mosaiqo\LaravelPayments\Events\SubscriptionPaymentSuccess;
use Mosaiqo\LaravelPayments\Events\SubscriptionResumed;
use Mosaiqo\LaravelPayments\Events\SubscriptionUnpaused;
use Mosaiqo\LaravelPayments\Events\SubscriptionUpdated;
use Mosaiqo\LaravelPayments\Events\WebhookFailed;
use Mosaiqo\LaravelPayments\Events\WebhookHandled;
use Mosaiqo\LaravelPayments\Events\WebhookReceived;
use Mosaiqo\LaravelPayments\Events\WebhookUnhandled;
use Mosaiqo\LaravelPayments\LaravelPayments;
use Mosaiqo\LaravelPayments\Models\Subscription;
use Tests\Fixtures\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

describe('Setup', function () {
    afterEach(function () {
        LaravelPayments::asyncWebhooks(false);
        LaravelPayments::storeWebhooks(false);
    });
    it('fails with incorrect signing secret', function () {
        Event::fake();
        config()->set([
            'payments.provider' => LaravelPayments::PROVIDER_STRIPE,
            'payments.providers.lemon-squeezy.signing_secret' => 'correct-signature',
        ]);

        $response = $this->postJson(route('payments.webhooks'), [
            'meta' => ['type' => 'fake_event'],
        ], [
            'X-Signature' => 'wrong-signature',
        ]);
        Event::assertNotDispatched(WebhookReceived::class);
        Event::assertNotDispatched(WebhookHandled::class);
        $response->assertStatus(403);
    })->skip(true, 'TBD');

    it('verifies the webhook with signing secret correctly', function () {
        Event::fake();
        config()->set([
            'payments.provider' => LaravelPayments::PROVIDER_STRIPE,
            'payments.providers.lemon-squeezy.signing_secret' => 'correct-signature',
        ]);

        $payload = ['meta' => ['type' => 'fake_event'],];
        $signature = hash_hmac('sha256', json_encode($payload), 'correct-signature');

        $response = $this->postJson(route('payments.webhooks'), $payload, [
            'X-Signature' => $signature,
        ]);

        $response->assertStatus(200);
        $response->assertSee('Webhook skipped no handle method in handler.');

        Event::assertDispatched(WebhookReceived::class);
        Event::assertNotDispatched(WebhookHandled::class);
    })->skip(true, 'TBD');

    it('returns 404 when provider is not set up correctly', function () {
        Event::fake();
        config()->set(['payments.provider' => null]);
        $response = $this->postJson(route('payments.webhooks'), [
            'type' => 'fake_event',
        ]);

        $response->assertStatus(404);

        Event::assertNotDispatched(WebhookReceived::class);
        Event::assertNotDispatched(WebhookHandled::class);
    });

    it('route is registered when provider is set correctly', function () {
        config()->set([
            'payments.provider' => LaravelPayments::PROVIDER_STRIPE,
            'payments.path' => 'payments',
        ]);
        expect(route('payments.webhooks'))->toEqual('http://localhost/payments/webhooks');
        $this->postJson(route('payments.webhooks'), [])->assertStatus(200);
    });

    it('handles response when event could not be handled due to missing handler', function () {
        Event::fake();
        LaravelPayments::$allowMissingProviders = true;
        config()->set('payments.provider', 'foo');
        $response = $this->postJson(route('payments.webhooks'), []);
        $response->assertStatus(200);
        $response->assertSee('Webhook received but no handler found.');
        Event::assertDispatched(WebhookReceived::class);
        Event::assertDispatched(WebhookUnhandled::class);
    });

    it('handles response when event could not be handled', function () {
        Event::fake();
        config()->set('payments.provider', LaravelPayments::PROVIDER_STRIPE);
        $response = $this->postJson(route('payments.webhooks'), [
            'type' => 'fake.event',
        ]);
        $response->assertStatus(200);
        $response->assertSee('Webhook skipped no handle method in handler.');
        Event::assertDispatched(WebhookReceived::class);
        Event::assertDispatched(WebhookFailed::class);
    });

    it('handles response when event name is invalid', function () {
        Event::fake();
        config()->set('payments.provider', LaravelPayments::PROVIDER_STRIPE);
        $response = $this->postJson(route('payments.webhooks'), [
            'type' => null,
        ]);
        $response->assertStatus(200);
        $response->assertSee('Webhook received but event name was not found.');
        Event::assertDispatched(WebhookReceived::class);
        Event::assertDispatched(WebhookFailed::class);
    });

    it('handles response when custom payload is invalid', function () {
        Event::fake();
        LaravelPayments::allowNonAuthenticatedBillables(false);
        config()->set('payments.provider', LaravelPayments::PROVIDER_STRIPE);
        $response = $this->postJson(route('payments.webhooks'), [
            'type' => 'customer.subscription.created',
            'data' => [
                'object' => [
                    'customer' => null,
                    'metadata' => ['custom_data' => null],
                ]
            ],
        ]);

        $response->assertStatus(200);
        $response->assertSee('Webhook skipped due to invalid custom data.');
        Event::assertDispatched(WebhookReceived::class);
        Event::assertDispatched(WebhookFailed::class);
    });

    it('handles saves webhook call to process it later', function () {
        Event::fake();
        config()->set('payments.provider', LaravelPayments::PROVIDER_STRIPE);
        LaravelPayments::asyncWebhooks();
        $response = $this->postJson(route('payments.webhooks'), [
            'type' => 'order_created',
            'data' => [
                'foo' => 'bar',
                'metadata' => ['custom_data' => null],
            ],
        ], ['x-foo' => 'bar']);

        $response->assertStatus(200);
        $response->assertSee('Webhook was handled.');
        $webhook = LaravelPayments::resolveWebhooksModel()::first();
        $this->assertNotNull($webhook);
        expect($webhook->body)
            ->toMatchArray([
                'type' => 'order_created',
                'data' => [
                    'foo' => 'bar',
                    'metadata' => ['custom_data' => null],
                ],
            ])
            ->and($webhook->headers['x-foo'])->toBe(['bar']);

        $this->assertDatabaseEmpty('payments_customers');
        $this->assertDatabaseEmpty('payments_orders');
        $this->assertDatabaseEmpty('payments_subscriptions');
        $this->assertDatabaseHas('payments_webhooks', [
            'id' => $webhook->id,
            'processed_at' => null,
        ]);

        Event::assertDispatched(WebhookReceived::class);
        Event::assertDispatched(WebhookHandled::class);
    })->skip(true, 'TBD');
});

describe('Orders', function () {
    it('handles order created event', function () {
        config()->set('payments.provider', LaravelPayments::PROVIDER_STRIPE);
        Event::fake();
        User::newFactory()->create(['id' => 1]);
        $response = $this->postJson(route('payments.webhooks'), [
            'meta' => [
                'type' => 'order_created',
                'custom_data' => ['billable_id' => 1, 'billable_type' => User::class],
            ],
            'data' => [
                'id' => 'ord_123',
                'attributes' => [
                    'customer_id' => 'cus_123',
                    'first_order_item' => [
                        'product_id' => 'pro_123',
                        'variant_id' => 'var_123',
                    ],
                    'identifier' => 'order_123',
                    'order_number' => '123',
                    'currency' => 'usd',
                    'subtotal' => 1000,
                    'discount_total' => 0,
                    'tax' => 50,
                    'total' => 1050,
                    'tax_name' => 'VAT',
                    'status' => 'paid',
                    'urls' => ['receipt' => 'http://example.com/receipt'],
                    'refunded' => false,
                    'refunded_at' => null,
                    'created_at' => now()->toIso8601String(),
                ],
            ],
        ]);

        $response->assertStatus(200);
        $response->assertSee('Webhook was handled.');

        $this->assertDatabaseHas('payments_customers', [
            'billable_id' => 1,
            'billable_type' => Relation::getMorphAlias(User::class),
            'provider_id' => 'cus_123',
        ]);

        $this->assertDatabaseHas('payments_orders', [
            'customer_id' => 'cus_123',
            'provider_id' => 'ord_123',
            'provider' => LaravelPayments::PROVIDER_STRIPE,
        ]);

        Event::assertDispatched(WebhookReceived::class);
        Event::assertDispatched(WebhookHandled::class);
        Event::assertDispatched(OrderCreated::class);
    });
    it('handles order created event for a non billables, eg. not logged in users', function () {
        config()->set('payments.provider', LaravelPayments::PROVIDER_STRIPE);
        LaravelPayments::allowNonAuthenticatedBillables();
        LaravelPayments::useBillableModel(User::class);

        Event::fake([
            OrderCreated::class,
            WebhookReceived::class,
            WebhookHandled::class,
        ]);
        $response = $this->postJson(route('payments.webhooks'), [
            'meta' => [
                'type' => 'order_created',
            ],
            'data' => [
                'id' => 'ord_123',
                'attributes' => [
                    'customer_id' => 'cus_123',
                    'first_order_item' => [
                        'product_id' => 'pro_123',
                        'variant_id' => 'var_123',
                    ],
                    'user_name' => 'John Doe',
                    'user_email' => 'john@doe.com',
                    'identifier' => 'order_123',
                    'order_number' => '123',
                    'currency' => 'usd',
                    'subtotal' => 1000,
                    'discount_total' => 0,
                    'tax' => 50,
                    'total' => 1050,
                    'tax_name' => 'VAT',
                    'status' => 'paid',
                    'urls' => ['receipt' => 'http://example.com/receipt'],
                    'refunded' => false,
                    'refunded_at' => null,
                    'created_at' => now()->toIso8601String(),
                ],
            ],
        ]);

        $response->assertStatus(200);
        $response->assertSee('Webhook was handled.');

        $this->assertDatabaseHas('payments_customers', [
            'provider_id' => 'cus_123',
            'provider' => LaravelPayments::PROVIDER_STRIPE,
        ]);

        $this->assertDatabaseHas('payments_orders', [
            'customer_id' => 'cus_123',
            'provider_id' => 'ord_123',
            'provider' => LaravelPayments::PROVIDER_STRIPE,
        ]);

        Event::assertDispatched(WebhookReceived::class);
        Event::assertDispatched(WebhookHandled::class);
        Event::assertDispatched(OrderCreated::class);
    });
    it('handles order refunded event when order is missing', function () {
        config()->set('payments.provider', LaravelPayments::PROVIDER_STRIPE);
        Event::fake();
        User::newFactory()->create(['id' => 1]);

        $response = $this->postJson(route('payments.webhooks'), [
            'meta' => [
                'type' => 'order_refunded',
                'custom_data' => ['billable_id' => 1, 'billable_type' => User::class],
            ],
            'data' => [
                'id' => 'ord_123',
                'attributes' => [
                    'customer_id' => 'cus_123',
                    'first_order_item' => [
                        'product_id' => 'pro_123',
                        'variant_id' => 'var_123',
                    ],
                    'identifier' => 'order_123',
                    'order_number' => '123',
                    'currency' => 'usd',
                    'subtotal' => 1000,
                    'discount_total' => 0,
                    'tax' => 50,
                    'total' => 1050,
                    'tax_name' => 'VAT',
                    'status' => 'refunded',
                    'urls' => ['receipt' => 'http://example.com/receipt'],
                    'refunded' => true,
                    'refunded_at' => now()->toIso8601String(),
                    'created_at' => now()->toIso8601String(),
                ],
            ],
        ]);
        $response->assertStatus(200);
        $response->assertSee('Webhook was handled.');

        $this->assertDatabaseHas('payments_customers', [
            'billable_id' => 1,
            'billable_type' => Relation::getMorphAlias(User::class),
            'provider_id' => 'cus_123',
        ]);

        $this->assertDatabaseEmpty('payments_orders');
        Event::assertDispatched(WebhookReceived::class);
        Event::assertDispatched(WebhookHandled::class);
        Event::assertDispatched(OrderRefunded::class, function ($event) {
            return $event->order === null;
        });
    });
    it('handles order refunded event when order is there', function () {
        config()->set('payments.provider', LaravelPayments::PROVIDER_STRIPE);
        Event::fake();
        User::newFactory()->create(['id' => 1]);
        OrderFactory::new([
            'provider' => LaravelPayments::PROVIDER_STRIPE,
            'customer_id' => 'cus_123',
            'product_id' => 'pro_123',
            'variant_id' => 'var_123',
            'provider_id' => 'ord_123',
            'billable_type' => Relation::getMorphAlias(User::class),
        ])->paid()->create();

        $response = $this->postJson(route('payments.webhooks'), [
            'meta' => [
                'type' => 'order_refunded',
                'custom_data' => ['billable_id' => 1, 'billable_type' => User::class],
            ],
            'data' => [
                'id' => 'ord_123',
                'attributes' => [
                    'customer_id' => 'cus_123',
                    'first_order_item' => [
                        'product_id' => 'pro_123',
                        'variant_id' => 'var_123',
                    ],
                    'identifier' => 'order_123',
                    'order_number' => '123',
                    'currency' => 'usd',
                    'subtotal' => 1000,
                    'discount_total' => 0,
                    'tax' => 50,
                    'total' => 1050,
                    'tax_name' => 'VAT',
                    'status' => 'refunded',
                    'urls' => ['receipt' => 'http://example.com/receipt'],
                    'refunded' => true,
                    'refunded_at' => now()->toIso8601String(),
                    'created_at' => now()->toIso8601String(),
                ],
            ],
        ]);
        $response->assertStatus(200);
        $response->assertSee('Webhook was handled.');

        $this->assertDatabaseHas('payments_customers', [
            'billable_id' => 1,
            'billable_type' => Relation::getMorphAlias(User::class),
            'provider_id' => 'cus_123',
        ]);

        $this->assertDatabaseHas('payments_orders', [
            'customer_id' => 'cus_123',
            'provider_id' => 'ord_123',
            'provider' => LaravelPayments::PROVIDER_STRIPE,
            'refunded' => true,
        ]);
        Event::assertDispatched(WebhookReceived::class);
        Event::assertDispatched(WebhookHandled::class);
        Event::assertDispatched(OrderRefunded::class, function ($event) {
            return $event->order !== null;
        });
    });
})->skip();;

describe('Subscriptions', function () {
    it('handles subscription created event', function () {
        config()->set('payments.provider', LaravelPayments::PROVIDER_STRIPE);
        $user = User::newFactory()->create(['id' => 1]);
        $user->createAsCustomer([
            'trial_ends_at' => now()->addMonth(),
        ]);

        Event::fake();

        $response = $this->postJson(route('payments.webhooks'), [
            'type' => 'customer.subscription.created',
            'data' => [
                'object' => [
                    'id' => 'sub_123',
                    'customer' => 'cus_123',
                    'cancel_at_period_end' => false,
                    'quantity' => 1,
                    'items' => [
                        'data' => [
                            [
                                'id' => 'bar',
                                'price' => ['id' => 'var_123', 'product' => 'pro_123'],
                                'quantity' => 10,
                            ],
                        ],
                    ],
                    'status' => 'active',
                    'trial_end' => null,
                    'metadata' => [
                        'custom_data' => [
                            'billable_id' => 1,
                            'billable_type' => User::class,
                        ],
                    ],
                ],
            ],
        ]);
        $response->assertStatus(200);
        $response->assertSee('Webhook was handled.');

        $this->assertDatabaseHas('payments_customers', [
            'billable_id' => 1,
            'billable_type' => Relation::getMorphAlias(User::class),
            'provider_id' => 'cus_123',
            'trial_ends_at' => null,
        ]);
        $this->assertDatabaseHas('payments_subscriptions', [
            'provider_id' => 'sub_123',
            'provider' => LaravelPayments::PROVIDER_STRIPE,
        ]);
        Event::assertDispatched(WebhookReceived::class);
        Event::assertDispatched(WebhookHandled::class);
        Event::assertDispatched(SubscriptionCreated::class);
    });

    it('handles subscription created event for multiple subscriptions', function () {
    })->skip(true, 'TBD');

    it('handles subscription created event for a non billables, eg. not logged in users', function () {
        config()->set('payments.provider', LaravelPayments::PROVIDER_STRIPE);
        $user = User::newFactory()->create(['id' => 1]);
        $user->createAsCustomer([
            'provider' => LaravelPayments::PROVIDER_STRIPE,
            'trial_ends_at' => now()->addMonth(),
        ]);

        LaravelPayments::allowNonAuthenticatedBillables();
        LaravelPayments::useBillableModel(User::class);

        Event::fake([
            SubscriptionCreated::class,
            WebhookReceived::class,
            WebhookHandled::class,
        ]);
        $response = $this->postJson(route('payments.webhooks'), [
            'type' => 'customer.subscription.created',
            'data' => [
                'object' => [
                    'id' => 'sub_123',
                    'customer' => 'cus_123',
                    'cancel_at_period_end' => false,
                    'quantity' => 1,
                    'items' => [
                        'data' => [
                            [
                                'id' => 'bar',
                                'price' => ['id' => 'var_123', 'product' => 'pro_123'],
                                'quantity' => 10,
                            ],
                        ],
                    ],
                    'status' => 'active',
                    'trial_end' => null,
                    'metadata' => [
                        'custom_data' => [
                            'billable_id' => 1,
                            'billable_type' => User::class,
                        ],
                    ],
                ],
            ],
        ]);

        $response->assertStatus(200);
        $response->assertSee('Webhook was handled.');

        $this->assertDatabaseHas('payments_customers', [
            'provider_id' => 'cus_123',
            'provider' => LaravelPayments::PROVIDER_STRIPE,
        ]);

        $this->assertDatabaseHas('payments_subscriptions', [
            'provider_id' => 'sub_123',
            'provider' => LaravelPayments::PROVIDER_STRIPE,
        ]);

        Event::assertDispatched(WebhookReceived::class);
        Event::assertDispatched(WebhookHandled::class);
        Event::assertDispatched(SubscriptionCreated::class);
    });

    it('handles subscription updated event', function () {
        config()->set('payments.provider', LaravelPayments::PROVIDER_STRIPE);
        Event::fake();
        User::newFactory()->create(['id' => 1]);
        SubscriptionFactory::new([
            'provider' => LaravelPayments::PROVIDER_STRIPE,
            'product_id' => 'pro_123',
            'variant_id' => 'var_123',
            'provider_id' => 'sub_123',
            'billable_id' => 1,
            'billable_type' => Relation::getMorphAlias(User::class),
        ])->trialing()->create();

        $response = $this->postJson(route('payments.webhooks'), [
            'id' => 'foo',
            'type' => 'customer.subscription.updated',
            'data' => [
                'object' => [
                    'id' => 'sub_123',
                    'customer' => 'cus_123',
                    'product_id' => 'pro_123',
                    'renews_at' => now()->addMonth()->toIso8601String(),
                    'cancel_at_period_end' => false,
                    'status' => Subscription::STATUS_ACTIVE,
                    'items' => [
                        'data' => [[
                            'id' => 'bar',
                            'price' => ['id' => 'var_123', 'product' => 'pro_123'],
                            'quantity' => 5,
                        ]],
                    ],
                    'metadata' => [
                        'custom_data' => ['billable_id' => 1, 'billable_type' => User::class],
                    ],
                ],
            ],
        ]);

        $response->assertStatus(200);
        $response->assertSee('Webhook was handled.');

        $this->assertDatabaseHas('payments_customers', [
            'billable_id' => 1,
            'billable_type' => Relation::getMorphAlias(User::class),
            'trial_ends_at' => null,
        ]);
        $this->assertDatabaseHas('payments_subscriptions', [
            'provider_id' => 'sub_123',
            'provider' => LaravelPayments::PROVIDER_STRIPE,
            'status' => Subscription::STATUS_ACTIVE,
        ]);
        Event::assertDispatched(WebhookReceived::class);
        Event::assertDispatched(WebhookHandled::class);
        Event::assertDispatched(SubscriptionUpdated::class);
    });

    it('handles subscription canceled event', function () {
        config()->set('payments.provider', LaravelPayments::PROVIDER_STRIPE);
        Event::fake();

        User::newFactory()->create(['id' => 1]);
        SubscriptionFactory::new([
            'provider' => LaravelPayments::PROVIDER_STRIPE,
            'product_id' => 'pro_123',
            'variant_id' => 'var_123',
            'provider_id' => 'sub_123',
            'billable_id' => 1,
            'billable_type' => Relation::getMorphAlias(User::class),
        ])->active()->create();

        $endsAt = now()->addMonth();
        $response = $this->postJson(route('payments.webhooks'), [
            'id' => 'foo',
            'type' => 'customer.subscription.updated',
            'data' => [
                'object' => [
                    'id' => 'sub_123',
                    'customer' => 'cus_123',
                    'cancel_at' => $endsAt->timestamp,
                    'cancel_at_period_end' => false,
                    'status' => 'canceled',
                    'items' => [
                        'data' => [[
                            'id' => 'bar',
                            'price' => ['id' => 'var_123', 'product' => 'pro_123'],
                            'quantity' => 5,
                        ]],
                    ],
                    'metadata' => [
                        'custom_data' => ['billable_id' => 1, 'billable_type' => User::class],
                    ],
                ],

            ],

            'meta' => [
                'type' => 'subscription_canceled',
                'custom_data' => ['billable_id' => 1, 'billable_type' => User::class],
            ],
        ]);

        $response->assertStatus(200);
        $response->assertSee('Webhook was handled.');

        $this->assertDatabaseHas('payments_customers', [
            'billable_id' => 1,
            'billable_type' => Relation::getMorphAlias(User::class),
            'trial_ends_at' => null,
        ]);
        $this->assertDatabaseHas('payments_subscriptions', [
            'provider_id' => 'sub_123',
            'provider' => LaravelPayments::PROVIDER_STRIPE,
            'status' => Subscription::STATUS_CANCELED,
            'ends_at' => Carbon::make($endsAt),
        ]);
        Event::assertDispatched(WebhookReceived::class);
        Event::assertDispatched(WebhookHandled::class);
        Event::assertDispatched(SubscriptionCanceled::class);
    });

    it('handles subscription resumed event', function () {
        config()->set('payments.provider', LaravelPayments::PROVIDER_STRIPE);
        Event::fake();
        User::newFactory()->create(['id' => 1]);
        SubscriptionFactory::new([
            'provider' => LaravelPayments::PROVIDER_STRIPE,
            'product_id' => 'pro_123',
            'variant_id' => 'var_123',
            'provider_id' => 'sub_123',
            'billable_id' => 1,
            'billable_type' => Relation::getMorphAlias(User::class),
        ])->canceled()->create();

        $response = $this->postJson(route('payments.webhooks'), [
            'id' => 'foo',
            'type' => 'customer.subscription.updated',
            'data' => [
                'object' => [
                    'id' => 'sub_123',
                    'customer' => 'cus_123',
                    'cancel_at_period_end' => false,
                    'status' => Subscription::STATUS_ACTIVE,
                    'items' => [
                        'data' => [[
                            'id' => 'sub_123',
                            'price' => ['id' => 'pri_123', 'product' => 'pro_123'],
                            'quantity' => 1,
                        ]],
                    ],
                    'metadata' => [
                        'custom_data' => ['billable_id' => 1, 'billable_type' => User::class],
                    ],
                ],
            ],
        ]);

        $response->assertStatus(200);
        $response->assertSee('Webhook was handled.');

        $this->assertDatabaseHas('payments_customers', [
            'billable_id' => 1,
            'billable_type' => Relation::getMorphAlias(User::class),
            'trial_ends_at' => null,
        ]);
        $this->assertDatabaseHas('payments_subscriptions', [
            'provider_id' => 'sub_123',
            'provider' => LaravelPayments::PROVIDER_STRIPE,
            'status' => Subscription::STATUS_ACTIVE,
            'ends_at' => null,
        ]);
        Event::assertDispatched(WebhookReceived::class);
        Event::assertDispatched(WebhookHandled::class);
        Event::assertDispatched(SubscriptionResumed::class);
    });

    it('handles subscription expired event', function () {
        config()->set('payments.provider', LaravelPayments::PROVIDER_STRIPE);
        Event::fake();
        User::newFactory()->create(['id' => 1]);
        SubscriptionFactory::new([
            'provider' => LaravelPayments::PROVIDER_STRIPE,
            'product_id' => 'pro_123',
            'variant_id' => 'var_123',
            'provider_id' => 'sub_123',
            'billable_id' => 1,
            'billable_type' => Relation::getMorphAlias(User::class),
        ])->active()->create();

        $endsAt = now()->addMonth()->toIso8601String();
        $response = $this->postJson(route('payments.webhooks'), [
            'meta' => [
                'type' => 'subscription_expired',
                'custom_data' => ['billable_id' => 1, 'billable_type' => User::class],
            ],

            'data' => [
                'id' => 'sub_123',
                'attributes' => [
                    'customer_id' => 'cus_123',
                    'product_id' => 'pro_123',
                    'variant_id' => 'var_123',
                    'status' => Subscription::STATUS_EXPIRED,
                    'trial_ends_at' => null,
                    'card_brand' => 'visa',
                    'card_last_four' => '4242',
                    'renews_at' => now()->addMonth()->toIso8601String(),
                    'ends_at' => null,
                    'created_at' => now()->toIso8601String(),
                ],
            ],
        ]);

        $response->assertStatus(200);
        $response->assertSee('Webhook was handled.');

        $this->assertDatabaseHas('payments_customers', [
            'billable_id' => 1,
            'billable_type' => Relation::getMorphAlias(User::class),
            'trial_ends_at' => null,
        ]);
        $this->assertDatabaseHas('payments_subscriptions', [
            'provider_id' => 'sub_123',
            'provider' => LaravelPayments::PROVIDER_STRIPE,
            'status' => Subscription::STATUS_EXPIRED,
            'ends_at' => null,
        ]);
        Event::assertDispatched(WebhookReceived::class);
        Event::assertDispatched(WebhookHandled::class);
        Event::assertDispatched(SubscriptionExpired::class);
    })
        ->skip();
    it('handles subscription paused event', function () {
        config()->set('payments.provider', LaravelPayments::PROVIDER_STRIPE);
        Event::fake();
        User::newFactory()->create(['id' => 1]);
        SubscriptionFactory::new([
            'provider' => LaravelPayments::PROVIDER_STRIPE,
            'product_id' => 'pro_123',
            'variant_id' => 'var_123',
            'provider_id' => 'sub_123',
            'billable_id' => 1,
            'billable_type' => Relation::getMorphAlias(User::class),
        ])->active()->create();

        $endsAt = now()->addMonth()->toIso8601String();
        $response = $this->postJson(route('payments.webhooks'), [
            'meta' => [
                'type' => 'subscription_paused',
                'custom_data' => ['billable_id' => 1, 'billable_type' => User::class],
            ],

            'data' => [
                'id' => 'sub_123',
                'attributes' => [
                    'customer_id' => 'cus_123',
                    'product_id' => 'pro_123',
                    'variant_id' => 'var_123',
                    'status' => Subscription::STATUS_PAUSED,
                    'trial_ends_at' => null,
                    'card_brand' => 'visa',
                    'card_last_four' => '4242',
                    'renews_at' => now()->addMonth()->toIso8601String(),
                    'ends_at' => null,
                    'created_at' => now()->toIso8601String(),
                ],
            ],
        ]);

        $response->assertStatus(200);
        $response->assertSee('Webhook was handled.');

        $this->assertDatabaseHas('payments_customers', [
            'billable_id' => 1,
            'billable_type' => Relation::getMorphAlias(User::class),
            'trial_ends_at' => null,
        ]);
        $this->assertDatabaseHas('payments_subscriptions', [
            'provider_id' => 'sub_123',
            'provider' => LaravelPayments::PROVIDER_STRIPE,
            'status' => Subscription::STATUS_PAUSED,
            'ends_at' => null,
        ]);
        Event::assertDispatched(WebhookReceived::class);
        Event::assertDispatched(WebhookHandled::class);
        Event::assertDispatched(SubscriptionPaused::class);
    })
        ->skip();
    it('handles subscription un-paused event', function () {
        config()->set('payments.provider', LaravelPayments::PROVIDER_STRIPE);
        Event::fake();
        User::newFactory()->create(['id' => 1]);
        SubscriptionFactory::new([
            'provider' => LaravelPayments::PROVIDER_STRIPE,
            'product_id' => 'pro_123',
            'variant_id' => 'var_123',
            'provider_id' => 'sub_123',
            'billable_id' => 1,
            'billable_type' => Relation::getMorphAlias(User::class),
        ])->paused()->create();

        $endsAt = now()->addMonth()->toIso8601String();
        $response = $this->postJson(route('payments.webhooks'), [
            'meta' => [
                'type' => 'subscription_unpaused',
                'custom_data' => ['billable_id' => 1, 'billable_type' => User::class],
            ],

            'data' => [
                'id' => 'sub_123',
                'attributes' => [
                    'customer_id' => 'cus_123',
                    'product_id' => 'pro_123',
                    'variant_id' => 'var_123',
                    'status' => Subscription::STATUS_ACTIVE,
                    'trial_ends_at' => null,
                    'card_brand' => 'visa',
                    'card_last_four' => '4242',
                    'renews_at' => now()->addMonth()->toIso8601String(),
                    'ends_at' => null,
                    'created_at' => now()->toIso8601String(),
                ],
            ],
        ]);

        $response->assertStatus(200);
        $response->assertSee('Webhook was handled.');

        $this->assertDatabaseHas('payments_customers', [
            'billable_id' => 1,
            'billable_type' => Relation::getMorphAlias(User::class),
            'trial_ends_at' => null,
        ]);
        $this->assertDatabaseHas('payments_subscriptions', [
            'provider_id' => 'sub_123',
            'provider' => LaravelPayments::PROVIDER_STRIPE,
            'status' => Subscription::STATUS_ACTIVE,
            'ends_at' => null,
        ]);
        Event::assertDispatched(WebhookReceived::class);
        Event::assertDispatched(WebhookHandled::class);
        Event::assertDispatched(SubscriptionUnpaused::class);
    })->skip();
    it('handles subscription payment success event', function () {
        config()->set('payments.provider', LaravelPayments::PROVIDER_STRIPE);
        Event::fake();
        User::newFactory()->create(['id' => 1]);
        SubscriptionFactory::new([
            'provider' => LaravelPayments::PROVIDER_STRIPE,
            'product_id' => 'pro_123',
            'variant_id' => 'var_123',
            'provider_id' => 'sub_123',
            'billable_id' => 1,
            'billable_type' => Relation::getMorphAlias(User::class),
        ])->active()->create();

        $endsAt = now()->addMonth()->toIso8601String();
        $response = $this->postJson(route('payments.webhooks'), [
            'meta' => [
                'type' => 'subscription_payment_success',
                'custom_data' => ['billable_id' => 1, 'billable_type' => User::class],
            ],

            'data' => [
                'id' => 'sub_123',
                'attributes' => [
                    'subscription_id' => 'sub_123',
                    'status' => Subscription::STATUS_ACTIVE,
                    'trial_ends_at' => null,
                    'card_brand' => 'visa',
                    'card_last_four' => '4242',
                    'renews_at' => now()->addMonth()->toIso8601String(),
                    'ends_at' => null,
                    'created_at' => now()->toIso8601String(),
                ],
            ],
        ]);

        $response->assertStatus(200);
        $response->assertSee('Webhook was handled.');
        Event::assertDispatched(WebhookReceived::class);
        Event::assertDispatched(WebhookHandled::class);
        Event::assertDispatched(SubscriptionPaymentSuccess::class);
    })->skip();
    it('handles subscription payment failed event', function () {
        config()->set('payments.provider', LaravelPayments::PROVIDER_STRIPE);
        Event::fake();
        User::newFactory()->create(['id' => 1]);
        SubscriptionFactory::new([
            'provider' => LaravelPayments::PROVIDER_STRIPE,
            'product_id' => 'pro_123',
            'variant_id' => 'var_123',
            'provider_id' => 'sub_123',
            'billable_id' => 1,
            'billable_type' => Relation::getMorphAlias(User::class),
        ])->active()->create();

        $endsAt = now()->addMonth()->toIso8601String();
        $response = $this->postJson(route('payments.webhooks'), [
            'meta' => [
                'type' => 'subscription_payment_failed',
                'custom_data' => ['billable_id' => 1, 'billable_type' => User::class],
            ],

            'data' => [
                'id' => 'sub_123',
                'attributes' => [
                    'subscription_id' => 'sub_123',
                    'status' => Subscription::STATUS_ACTIVE,
                    'trial_ends_at' => null,
                    'card_brand' => 'visa',
                    'card_last_four' => '4242',
                    'renews_at' => now()->addMonth()->toIso8601String(),
                    'ends_at' => null,
                    'created_at' => now()->toIso8601String(),
                ],
            ],
        ]);

        $response->assertStatus(200);
        $response->assertSee('Webhook was handled.');
        Event::assertDispatched(WebhookReceived::class);
        Event::assertDispatched(WebhookHandled::class);
        Event::assertDispatched(SubscriptionPaymentFailed::class);
    })->skip();
    it('handles subscription payment recovered event', function () {
        config()->set('payments.provider', LaravelPayments::PROVIDER_STRIPE);
        Event::fake();
        User::newFactory()->create(['id' => 1]);
        SubscriptionFactory::new([
            'provider' => LaravelPayments::PROVIDER_STRIPE,
            'product_id' => 'pro_123',
            'variant_id' => 'var_123',
            'provider_id' => 'sub_123',
            'billable_id' => 1,
            'billable_type' => Relation::getMorphAlias(User::class),
        ])->active()->create();

        $endsAt = now()->addMonth()->toIso8601String();
        $response = $this->postJson(route('payments.webhooks'), [
            'meta' => [
                'type' => 'subscription_payment_recovered',
                'custom_data' => ['billable_id' => 1, 'billable_type' => User::class],
            ],

            'data' => [
                'id' => 'sub_123',
                'attributes' => [
                    'subscription_id' => 'sub_123',
                    'status' => Subscription::STATUS_ACTIVE,
                    'trial_ends_at' => null,
                    'card_brand' => 'visa',
                    'card_last_four' => '4242',
                    'renews_at' => now()->addMonth()->toIso8601String(),
                    'ends_at' => null,
                    'created_at' => now()->toIso8601String(),
                ],
            ],
        ]);

        $response->assertStatus(200);
        $response->assertSee('Webhook was handled.');
        Event::assertDispatched(WebhookReceived::class);
        Event::assertDispatched(WebhookHandled::class);
        Event::assertDispatched(SubscriptionPaymentRecovered::class);
    })->skip();
}); //->only();
