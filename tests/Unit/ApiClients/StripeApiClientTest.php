<?php

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Mosaiqo\LaravelPayments\ApiClients\StripeApiClient;
use Mosaiqo\LaravelPayments\Exceptions\ApiError;
use Mosaiqo\LaravelPayments\Exceptions\MissingApiKey;
use Tests\TestCase;

uses(TestCase::class);

it('needs an api key to create a new client', function () {
    $this->expectExceptionMessage('You must provide a valid Stripe API key to make requests.');
    $this->expectException(MissingApiKey::class);
    new StripeApiClient();
});

it('can make a new client', function () {
    $client = StripeApiClient::make('api-key');
    expect($client)->toBeInstanceOf(StripeApiClient::class);
});
