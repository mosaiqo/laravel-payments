<?php

namespace Mosaiqo\LaravelPayments\Concerns;

use Mosaiqo\LaravelPayments\LaravelPayments;

trait ManagesSubscriptions
{
    /**
     * Get all of the subscriptions for the billable.
     */
    public function subscriptions(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        $model = LaravelPayments::resolveSubscriptionModel();
        return $this->morphMany($model, 'billable')->orderByDesc('created_at');
    }
}
