<?php

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Mosaiqo\LaravelPayments\ApiClients\LemonSqueezyApiClient;
use Mosaiqo\LaravelPayments\Exceptions\ApiError;
use Mosaiqo\LaravelPayments\Exceptions\MissingApiKey;
use Tests\TestCase;

uses(TestCase::class);

it('needs an api key to create a new client', function () {
    $this->expectExceptionMessage('You must provide a valid LemonSqueezy API key to make requests.');
    $this->expectException(MissingApiKey::class);
    new LemonSqueezyApiClient();
});

it('can make a new client', function () {
    $client = LemonSqueezyApiClient::make('api-key');
    expect($client)->toBeInstanceOf(LemonSqueezyApiClient::class);
});

it('can make a get request', function () {
    Http::fake(['api.lemonsqueezy.com/*' => function (Request $request) {
            expect($request->url())->toBe('https://api.lemonsqueezy.com/v1/test?data=test');
            expect($request->data())->toMatchArray(['data' => 'test']);
            expect($request->method())->toBe('GET');
            return Http::response(['message' => 'ok']);
        },
    ]);
    $client = LemonSqueezyApiClient::make('api-key');
    $response = $client->get('/test', ['data' => 'test']);
    expect($response->json())->toMatchArray(['message' => 'ok']);
});

it('can make a post request', function () {
    Http::fake(['api.lemonsqueezy.com/*' => function (Request $request) {
            expect($request->url())->toBe('https://api.lemonsqueezy.com/v1/test');
            expect($request->data())->toMatchArray(['data' => 'test']);
            expect($request->method())->toBe('POST');
            return Http::response(['message' => 'ok']);
        },
    ]);
    $client = LemonSqueezyApiClient::make('api-key');
    $response = $client->post('/test', ['data' => 'test']);
    expect($response->json())->toMatchArray(['message' => 'ok']);
});

it('can make a put request', function () {
    Http::fake(['api.lemonsqueezy.com/*' => function (Request $request) {
            expect($request->url())->toBe('https://api.lemonsqueezy.com/v1/test/1');
            expect($request->data())->toMatchArray(['data' => 'test']);
            expect($request->method())->toBe('PUT');
            return Http::response(['message' => 'ok']);
        },
    ]);
    $client = LemonSqueezyApiClient::make('api-key');
    $response = $client->put('/test/1', ['data' => 'test']);
    expect($response->json())->toMatchArray(['message' => 'ok']);
});

it('can make a patch request', function () {
    Http::fake(['api.lemonsqueezy.com/*' => function (Request $request) {
            expect($request->url())->toBe('https://api.lemonsqueezy.com/v1/test/1');
            expect($request->data())->toMatchArray(['data' => 'test']);
            expect($request->method())->toBe('PATCH');
            return Http::response(['message' => 'ok']);
        },
    ]);
    $client = LemonSqueezyApiClient::make('api-key');
    $response = $client->patch('/test/1', ['data' => 'test']);
    expect($response->json())->toMatchArray(['message' => 'ok']);
});


it('can make a head request', function () {
    Http::fake(['api.lemonsqueezy.com/*' => function (Request $request) {
            expect($request->url())->toBe('https://api.lemonsqueezy.com/v1/test/1?data=test');
            expect($request->data())->toMatchArray(['data' => 'test']);
            expect($request->method())->toBe('HEAD');
            return Http::response(['message' => 'ok']);
        },
    ]);
    $client = LemonSqueezyApiClient::make('api-key');
    $response = $client->head('/test/1', ['data' => 'test']);
    expect($response->json())->toMatchArray(['message' => 'ok']);
});

it('captures api errors correctly', function () {
    $this->expectException(ApiError::class);
    Http::fake(['api.lemonsqueezy.com/*' => Http::response(['errors' => [
        ['detail' => 'An error occurred', 'status' => 500 ],
    ]], 500)]);
    $client = LemonSqueezyApiClient::make('api-key');
    $response = $client->get('/test/1');
});
