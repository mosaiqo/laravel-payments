<?php

use Mosaiqo\LaravelPayments\ApiClients\ApiClient;
use Mosaiqo\LaravelPayments\ApiClients\StripeApiClient;
use Mosaiqo\LaravelPayments\ApiClients\LemonSqueezyApiClient;
use Mosaiqo\LaravelPayments\Exceptions\InvalidProvider;
use Mosaiqo\LaravelPayments\LaravelPayments;

uses(\Tests\TestCase::class);

describe('General', function () {
    it('it can not be instantiated without a proper provider', function () {
        $this->expectException(InvalidProvider::class);
        $client = ApiClient::forProvider('fake_provider');
    });
});
describe('Provider: LemonSqueezy', function () {
    it('it returns correct api client instance for the given provider', function () {
        $client = ApiClient::forProvider(LaravelPayments::PROVIDER_LEMON_SQUEEZY);
        expect($client)->toBeInstanceOf(LemonSqueezyApiClient::class);
    });

    it('it returns correct api client instance with config', function () {
        config()->set([
            'payments.provider' => LaravelPayments::PROVIDER_LEMON_SQUEEZY,
            'payments.providers.lemon-squeezy.store' => 'store_12345',
            'payments.providers.lemon-squeezy.api_key' => 'fake_key',
        ]);
        $client = ApiClient::make();
        expect($client)->toBeInstanceOf(LemonSqueezyApiClient::class);
    });
})->group('lemon-squeezy');


describe('Provider: Stripe', function () {
    it('it returns correct api client instance for the given provider', function () {
        $client = ApiClient::forProvider(LaravelPayments::PROVIDER_STRIPE);
        expect($client)->toBeInstanceOf(StripeApiClient::class);
    });

    it('it returns correct api client instance with config', function () {
        config()->set([
            'payments.provider' => LaravelPayments::PROVIDER_STRIPE,
            'payments.providers.stripe.api_key' => 'fake_key',
        ]);
        $client = ApiClient::make();
        expect($client)->toBeInstanceOf(StripeApiClient::class);
    });
})->group('stripe');
