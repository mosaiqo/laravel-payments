<?php

namespace Mosaiqo\LaravelPayments\WebhookHandlers;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Str;
use Mosaiqo\LaravelPayments\Events\SubscriptionCanceled;
use Mosaiqo\LaravelPayments\Events\SubscriptionCreated;
use Mosaiqo\LaravelPayments\Events\SubscriptionResumed;
use Mosaiqo\LaravelPayments\Events\SubscriptionUpdated;
use Mosaiqo\LaravelPayments\Exceptions\HandleEventMethodNotImplemented;
use Mosaiqo\LaravelPayments\Exceptions\InvalidCustomPayload;
use Mosaiqo\LaravelPayments\Exceptions\InvalidEventName;
use Mosaiqo\LaravelPayments\LaravelPayments;
use Mosaiqo\LaravelPayments\Models\Subscription;

class StripeWebhookHandler
{
    public function __construct()
    {
    }

    /**
     * Handle the incoming webhook payload.
     *
     * @param array $payload
     *
     * @return void
     * @throws \Mosaiqo\LaravelPayments\Exceptions\HandleEventMethodNotImplemented
     * @throws \Mosaiqo\LaravelPayments\Exceptions\InvalidEventName
     */
    public function handle(array $payload): void
    {
        if (!isset($payload['type'])) {
            throw new InvalidEventName;
        }

        $eventName = $payload['type'];
        $method = 'handle' . Str::studly(str_replace('.', '_', $eventName)) . 'Event';

        if (method_exists($this, $method)) {
            $this->{$method}($payload);
        } else {
            throw new HandleEventMethodNotImplemented('Not implemented');
        }
    }

    /**
     * @param $payload
     *
     * @return void
     * @throws \Mosaiqo\LaravelPayments\Exceptions\InvalidCustomPayload
     */
    protected function handleCustomerSubscriptionCreatedEvent(array $payload): void
    {
        $customer = $this->resolveBillable($payload);
//        if (LaravelPayments::areNonAuthenticatedBillablesAllowed()) {
//            $model = app(LaravelPayments::resolveSubscriptionModel());
//        } else {
//            $model = $billable->subscriptions();
//        }

//        $model = $billable->subscriptions();

        $data = $payload['data']['object'] ?? null;
        $custom = $data['metadata']['custom_data'] ?? null;
        $firstItem = $data['items']['data'][0];
        $isSinglePrice = count($data['items']['data']) === 1;

        if (isset($data['trial_end'])) {
            $trialEndsAt = Carbon::createFromTimestamp($data['trial_end']);
        } else {
            $trialEndsAt = null;
        }

        $subscription = $customer->subscriptions()->create([
            'type' => $custom['type'] ?? $custom['name'] ?? Subscription::DEFAULT_TYPE,
            'provider_id' => $data['id'],
            'customer_id' => $data['customer'],
            'provider' => LaravelPayments::PROVIDER_STRIPE,
            'status' => $data['status'],
            'product_id' => $isSinglePrice ? (string)  $firstItem['price']['product'] : null,
            'variant_id' => $isSinglePrice ? (string) $firstItem['price']['id'] : null,
            'trial_ends_at' => $trialEndsAt,
            'ends_at' => isset($data['ends_at']) ? Carbon::make($data['ends_at']) : null,
        ]);

        // Terminate the billable's generic trial at the model level if it exists...
        if (!is_null($customer?->trial_ends_at)) {
            $customer?->update(['trial_ends_at' => null]);
        }

        // Set the billable's provide id if it was on generic trial at the model level
        if (is_null($customer?->provider_id)) {
            $customer?->update([
                'provider_id' => $data['customer'],
            ]);
        }

        SubscriptionCreated::dispatch($customer, $subscription, $payload);
    }

    protected function handleCustomerSubscriptionUpdatedEvent(array $payload): void {
        $customer = $this->resolveBillable($payload);

        $data = $payload['data']['object'] ?? null;
        $providerId = $data['id'];
        $custom = $data['metadata']['custom_data'] ?? null;
        $firstItem = $data['items']['data'][0];
        $isSinglePrice = count($data['items']['data']) === 1;

        $subscription = $this->findSubscription($providerId);

        if (isset($data['trial_end'])) {
            $trialEndsAt = Carbon::createFromTimestamp($data['trial_end']);
        } else {
            $trialEndsAt = null;
        }

        $previousStatus = $subscription->status;

        $subscription->update([
            'type' => $subscription->type ?? $custom['type'] ?? $custom['name'] ?? Subscription::DEFAULT_TYPE,
            'provider_id' => $data['id'],
            'provider' => LaravelPayments::PROVIDER_STRIPE,
            'status' => $data['status'] ?? $subscription->status,
            'product_id' => $isSinglePrice ? (string)  $firstItem['price']['product'] : null,
            'variant_id' => $isSinglePrice ? (string) $firstItem['price']['id'] : null,
            'trial_ends_at' => $trialEndsAt,
            'ends_at' => isset($data['ends_at']) ? Carbon::make($data['ends_at']) : null,
        ]);

        // Cancellation date...
        if ($data['cancel_at_period_end'] ?? false) {
            $subscription->ends_at = $subscription->onTrial()
                ? $subscription->trial_ends_at
                : Carbon::createFromTimestamp($data['current_period_end']);
        } elseif (isset($data['cancel_at']) || isset($data['canceled_at'])) {
            $subscription->ends_at = Carbon::createFromTimestamp($data['cancel_at'] ?? $data['canceled_at']);
        } else {
            $subscription->ends_at = null;
        }

        $subscription->save();

        // Terminate the billable's generic trial at the model level if it exists...
        if (!is_null($customer?->trial_ends_at)) {
            $customer?->update(['trial_ends_at' => null]);
        }

        // Set the billable's provide id if it was on generic trial at the model level
        if (is_null($customer?->provider_id)) {
            $customer?->update([
                'provider_id' => $data['customer']
            ]);
        }

        $subscription->fresh();

        if ($previousStatus !== Subscription::STATUS_CANCELED && $subscription->status === Subscription::STATUS_CANCELED) {
            SubscriptionCanceled::dispatch($customer, $subscription, $payload);
        } else if ($previousStatus === Subscription::STATUS_CANCELED && $subscription->status === Subscription::STATUS_ACTIVE) {
            SubscriptionResumed::dispatch($customer, $subscription, $payload);
        } else {
            SubscriptionUpdated::dispatch($customer, $subscription, $payload);
        }

    }

    /**
     * Resolve the billable entity from the payload.
     *
     * @param array $payload
     * @return ?Model
     * @throws \Mosaiqo\LaravelPayments\Exceptions\InvalidCustomPayload
     */
    private function resolveBillable(array $payload): ?Model
    {
        $custom = $payload['data']['object']['metadata']['custom_data'] ?? null;

        if (!LaravelPayments::areNonAuthenticatedBillablesAllowed()) {
            if (!isset($custom) || !is_array($custom) || !isset($custom['billable_id'], $custom['billable_type'])) {
                throw new InvalidCustomPayload;
            }
        }

        if (LaravelPayments::areNonAuthenticatedBillablesAllowed()) {
            $custom = $custom ?? [
                'billable_id' => null,
                'billable_type' => null,
            ];
        }

        return $this->findOrCreateCustomer(
            $custom['billable_id'],
            (string) $custom['billable_type'],
            (string) $payload['data']['object']['customer'],
            $payload
        );
    }

    /**
     * Find or create a customer.
     *
     * @param int|string $billableId
     * @param string $billableType
     * @param string $customerId
     * @return ?Model
     */
    private function findOrCreateCustomer(int|string|null $billableId, ?string $billableType, string $customerId, array $payload = null): ?Model
    {
        $model = LaravelPayments::resolveCustomerModel();

        $customer = $model::firstOrCreate([
            'billable_id' => $billableId,
            'billable_type' => Relation::getMorphAlias($billableType),
        ], [
            'provider_id' => $customerId,
        ]);

//        if(!$customer->name || !$customer->email){
//            $customer->update([
//                'name' => $attributes['user_name'],
//                'email' => $attributes['user_email'],
//            ]);
//        }
//


        if ($customer->wasRecentlyCreated) {
            $customer->update([
                'provider_id' => $customerId,
                'provider' => LaravelPayments::PROVIDER_STRIPE,
            ]);
        }


        return $customer ?? null;
    }

    /**
     * Find a subscription by the provider id.
     *
     * @param string $providerId
     * @return ?Model
     */
    private function findSubscription(string $providerId): ?Model
    {
        $model = LaravelPayments::resolveSubscriptionModel();
        return $model::firstWhere('provider_id', $providerId);
    }

}
