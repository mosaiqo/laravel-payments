<?php

namespace Mosaiqo\LaravelPayments\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Mosaiqo\LaravelPayments\Models\Customer;
use Mosaiqo\LaravelPayments\Models\Subscription;

class SubscriptionExpired
{
    use Dispatchable, SerializesModels;

    /**
     * The customer entity.
     */
    public Customer $customer;

    /**
     * The subscription instance.
     */
    public Subscription $subscription;

    /**
     * The payload array.
     */
    public array $payload;

    public function __construct(Customer $customer, Subscription $subscription, array $payload)
    {
        $this->customer = $customer;
        $this->subscription = $subscription;
        $this->payload = $payload;
    }
}
