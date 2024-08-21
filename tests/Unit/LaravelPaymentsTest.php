<?php

use Mosaiqo\LaravelPayments\Exceptions\MissingProvider;
use Mosaiqo\LaravelPayments\Http\Middleware\LemonSqueezyVerifyWebhookSignature;
use Mosaiqo\LaravelPayments\Http\Middleware\StripeVerifyWebhookSignature;
use Mosaiqo\LaravelPayments\LaravelPayments;
use Mosaiqo\LaravelPayments\Models\Customer;
use Mosaiqo\LaravelPayments\Models\Order;
use Mosaiqo\LaravelPayments\Models\Subscription;
use Mosaiqo\LaravelPayments\WebhookHandlers\LemonSqueezyWebhookHandler;
use Mosaiqo\LaravelPayments\WebhookHandlers\StripeWebhookHandler;

uses(\Tests\TestCase::class);

it('can format given amount into displayable currency', function () {
    config()->set([
        'payments.provider' => LaravelPayments::PROVIDER_LEMON_SQUEEZY
    ]);
    expect(LaravelPayments::formatAmount(999, 'USD'))->toBe('$9.99');
    expect(LaravelPayments::formatAmount(999, 'EUR'))->toBe('€9.99');
    expect(LaravelPayments::formatAmount(999, 'GBP'))->toBe('£9.99');

    expect(LaravelPayments::formatAmount(999, 'USD', 'ES'))->toBe('9,99 US$');
    expect(LaravelPayments::formatAmount(999, 'EUR', 'ES'))->toBe('9,99 €');
    expect(LaravelPayments::formatAmount(999, 'GBP', 'ES'))->toBe('9,99 GBP');


    expect(LaravelPayments::formatAmount(999, 'USD', options: ['min_fraction_digits' => 4]))->toBe('$9.9900');
});

it('return null if no signature middleware could be found by provider', function () {
    expect(LaravelPayments::resolveProviderSignatureMiddleware())->toBeNull();
});

it('expects a correct provider config to be set', function () {
    $this->expectException(MissingProvider::class);
    LaravelPayments::resolveProviderConfig();
});


describe('General', function () {
    afterEach(function () {
        LaravelPayments::useCustomerModel(Customer::class);
        LaravelPayments::useOrderModel(Order::class);
        LaravelPayments::useSubscriptionModel(Subscription::class);
    });

    it('can set a different customer model', function () {
        LaravelPayments::useCustomerModel('FakeCustomerModel');
        expect(LaravelPayments::resolveCustomerModel())->toBe('FakeCustomerModel');
    });

    it('can set a different order model', function () {
        LaravelPayments::useOrderModel('FakeOrderModel');
        expect(LaravelPayments::resolveOrderModel())->toBe('FakeOrderModel');
    });

    it('can set a different subscription model', function () {
        LaravelPayments::useSubscriptionModel('FakeSubscriptionModel');
        expect(LaravelPayments::resolveSubscriptionModel())->toBe('FakeSubscriptionModel');
    });
});

describe('Provider: LemonSqueezy', function () {
    beforeEach(function () {
        config()->set([
            'payments.provider' => LaravelPayments::PROVIDER_LEMON_SQUEEZY
        ]);
    });
    afterEach(function () {
        LaravelPayments::useLemonSqueezyWebhookHandler(LemonSqueezyWebhookHandler::class);
        LaravelPayments::useLemonSqueezyVerifyWebhookSignature(LemonSqueezyVerifyWebhookSignature::class);
    });

    it('can set a different webhook handler', function () {
        LaravelPayments::useLemonSqueezyWebhookHandler('FakeWebhookHandler');
        expect(LaravelPayments::resolveProviderWebhookHandler())->toBe('FakeWebhookHandler');
    });

    it('can set a different verify webhook signature', function () {
        LaravelPayments::useLemonSqueezyVerifyWebhookSignature('FakeVerifyWebhookSignature');
        expect(LaravelPayments::resolveProviderSignatureMiddleware())->toBe('FakeVerifyWebhookSignature');
    });
})->group('lemon-squeezy');

describe('Provider: Stripe', function () {
    beforeEach(function () {
        config()->set([
            'payments.provider' => LaravelPayments::PROVIDER_STRIPE
        ]);
    });
    afterEach(function () {
        LaravelPayments::useStripeWebhookHandler(StripeWebhookHandler::class);
        LaravelPayments::useStripeVerifyWebhookSignature(StripeVerifyWebhookSignature::class);
    });

    it('can set a different webhook handler', function () {
        LaravelPayments::useStripeWebhookHandler('FakeWebhookHandler');
        expect(LaravelPayments::resolveProviderWebhookHandler())->toBe('FakeWebhookHandler');
    });

    it('can set a different verify webhook signature', function () {
        LaravelPayments::useStripeVerifyWebhookSignature('FakeVerifyWebhookSignature');
        expect(LaravelPayments::resolveProviderSignatureMiddleware())->toBe('FakeVerifyWebhookSignature');
    });
})->group('stripe');
