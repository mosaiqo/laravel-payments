<?php

namespace Mosaiqo\LaravelPayments\Concerns;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Laravel\Cashier\SubscriptionItem;
use Mosaiqo\LaravelPayments\LaravelPayments;
use Mosaiqo\LaravelPayments\Models\Subscription;

trait ManagesSubscriptions
{
    /**
     * Get all of the subscriptions for the billable.
     */
    public function subscriptions()
    {
        return $this->customer?->subscriptions()
            ->orderByDesc('created_at');
    }

    /**
     * Get the subscription related to the billable model.
     */
    public function subscription(string $type = 'default'): ?Subscription
    {
        return $this->subscriptions()?->where('type', $type)->first();
    }

    /**
     * Determine if the subscription model has a given subscription.
     *
     * @param string $type
     * @param ?int   $price
     *
     * @return bool
     */
    public function subscribed(string $type = 'default', ?int $price = null): bool
    {
        $subscription = $this->subscription($type);

        if (!$subscription || !$subscription->valid()) {
            return false;
        }

        return !$price || $subscription->hasPrice($price);
    }
}
