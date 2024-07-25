<?php

use Mosaiqo\LaravelPayments\ApiClients\ApiClient;
use Mosaiqo\LaravelPayments\ApiClients\LemonSqueezyApiClient;
use Mosaiqo\LaravelPayments\Exceptions\InvalidProvider;
use Mosaiqo\LaravelPayments\LaravelPayments;

uses(\Tests\TestCase::class);

it('it returns correct api client instance for the given provider', function () {
    $client = ApiClient::forProvider(LaravelPayments::PROVIDER_LEMON_SQUEEZY);
    expect($client)->toBeInstanceOf(LemonSqueezyApiClient::class);
});

it('it can not be instantiated without a proper provider', function () {
    $this->expectException(InvalidProvider::class);
    $client = ApiClient::forProvider('fake_provider');
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
