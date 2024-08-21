<?php

namespace Mosaiqo\LaravelPayments\ApiClients;

use Carbon\Carbon;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Mosaiqo\LaravelPayments\Exceptions\ApiError;
use Mosaiqo\LaravelPayments\Exceptions\MissingApiKey;
use Mosaiqo\LaravelPayments\LaravelPayments;
use Stripe\StripeClient;

class StripeApiClient
{
    protected StripeClient $client;

    /**
     * @throws \Mosaiqo\LaravelPayments\Exceptions\MissingApiKey
     */
    public function __construct(string $apiKey = null, array $options = [])
    {
        if (!$apiKey) {
            throw MissingApiKey::stripe();
        }

        $this->client = new StripeClient($apiKey, $options);
    }

    /**
     * @throws \Mosaiqo\LaravelPayments\Exceptions\MissingApiKey
     */
    public static function make(string $apiKey = null): static
    {
        return new static($apiKey);
    }

    public function checkout($variant, $discountCode = null)
    {
        return $this->client->paymentLinks->create([
            'line_items' => [
                [ 'price' => $variant, 'quantity' => 1 ],
            ]
        ]);
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
}
