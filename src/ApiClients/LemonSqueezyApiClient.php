<?php

namespace Mosaiqo\LaravelPayments\ApiClients;

use Carbon\Carbon;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Mosaiqo\LaravelPayments\Exceptions\ApiError;
use Mosaiqo\LaravelPayments\Exceptions\MissingApiKey;
use Mosaiqo\LaravelPayments\LaravelPayments;

class LemonSqueezyApiClient
{
    protected PendingRequest $client;

    /**
     * @throws \Mosaiqo\LaravelPayments\Exceptions\MissingApiKey
     */
    public function __construct(string $apiKey = null)
    {
        if (!$apiKey) {
            throw MissingApiKey::lemonSqueezy();
        }

        $this->client =  Http::withToken($apiKey)
            ->withUserAgent('Mosaiqo\LaravelPayments/'. LaravelPayments::VERSION)
            ->accept('application/vnd.api+json')
            ->contentType('application/vnd.api+json')
            ->baseUrl(LaravelPayments::API_URL);
    }

    /**
     * @throws \Mosaiqo\LaravelPayments\Exceptions\MissingApiKey
     */
    public static function make(string $apiKey = null): static
    {
        return new static($apiKey);
    }

    /**
     * @param array $payload
     *
     * @return mixed
     * @throws \Mosaiqo\LaravelPayments\Exceptions\ApiError
     */
    public function createCheckout(array $payload): mixed
    {
        return $this->post('/checkouts', $payload);
    }

    /**
     * @throws \Mosaiqo\LaravelPayments\Exceptions\ApiError
     */
    public function getCustomer(string | int $customerId): mixed
    {
        return $this->get('/customers/' . $customerId);
    }

    public function swapSubscription(string $id, array $payload): mixed
    {
        return $this->patch("subscriptions/{$id}", $payload);
    }

    public function anchorSubscriptionBillingCycleOn($id, ?int $date): mixed
    {
        return $this->patch("subscriptions/{$id}", [
            'data' => [
                'type' => 'subscriptions',
                'id' => $id,
                'attributes' => [
                    'billing_anchor' => $date,
                ],
            ],
        ]);
    }

    public function cancelSubscription($id): mixed
    {
        return $this->delete("subscriptions/{$id}");
    }

    public function resumeSubscription($id): mixed
    {
        return $this->patch("subscriptions/{$id}");
    }

    public function pauseSubscription($id, $mode, ?\DateTimeInterface $date = null): mixed
    {
        return $this->patch("subscriptions/{$id}", [
            'data' => [
                'type' => 'subscriptions',
                'id' => $id,
                'attributes' => [
                    'pause' => [
                        'mode' => $mode,
                        'resumes_at' => $date ? Carbon::instance($date)->toIso8601String() : null,
                    ],
                ],
            ],
        ]);
    }

    public function unpauseSubscription($id): mixed
    {
        return $this->patch("subscriptions/{$id}", [
            'data' => [
                'type' => 'subscriptions',
                'id' => $id,
                'attributes' => [
                    'pause' => null,
                ],
            ],
        ]);
    }

    /**
     * @throws \Mosaiqo\LaravelPayments\Exceptions\ApiError
     */
    public function getSubscription($id): mixed
    {
        return $this->get("subscriptions/{$id}");
    }

    /**
     * @throws \Mosaiqo\LaravelPayments\Exceptions\ApiError
     */
    public function get(string $uri, array $payload = []): mixed
    {
        return $this->request('get', $uri, $payload);
    }

    /**
     * @throws \Mosaiqo\LaravelPayments\Exceptions\ApiError
     */
    public function post(string $uri, array $payload = []): mixed
    {
        return $this->request('post', $uri, $payload);
    }

    /**
     * @throws \Mosaiqo\LaravelPayments\Exceptions\ApiError
     */
    public function put(string $uri, array $payload = []): mixed
    {
        return $this->request('put', $uri, $payload);
    }

    /**
     * @throws \Mosaiqo\LaravelPayments\Exceptions\ApiError
     */
    public function delete(string $uri, array $payload = []): mixed
    {
        return $this->request('delete', $uri, $payload);
    }

    /**
     * @throws \Mosaiqo\LaravelPayments\Exceptions\ApiError
     */
    public function patch(string $uri, array $payload = []): mixed
    {
        return $this->request('patch', $uri, $payload);
    }

    /**
     * @throws \Mosaiqo\LaravelPayments\Exceptions\ApiError
     */
    public function head(string $uri, array $payload = []): mixed
    {
        return $this->request('head', $uri, $payload);
    }


    /**
     * @throws \Mosaiqo\LaravelPayments\Exceptions\ApiError
     */
    public function request(string $method, string $uri, array $payload = []): mixed
    {
        $response = $this->client->$method($uri, $payload);

        if ($response->failed()) {

            $code = $response['errors'][0]['status'] ?? 200;
            throw new ApiError(
                $response['errors'][0]['detail'] ?? $response['message'],
                    (int) $code ,
            );
        }

        return $response;
    }

}
