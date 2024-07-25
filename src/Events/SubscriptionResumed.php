<?php

namespace Mosaiqo\LaravelPayments\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Mosaiqo\LaravelPayments\Models\Subscription;

class SubscriptionResumed
{
    use Dispatchable, SerializesModels;

    /**
     * The billable entity.
     */
    public Model $billable;

    /**
     * The subscription instance.
     */
    public Subscription $subscription;

    /**
     * The payload array.
     */
    public array $payload;

    public function __construct(Model $billable, Subscription $subscription, array $payload)
    {
        $this->billable = $billable;
        $this->subscription = $subscription;
        $this->payload = $payload;
    }
}
