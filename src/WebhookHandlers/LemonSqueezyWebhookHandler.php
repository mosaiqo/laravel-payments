<?php

namespace Mosaiqo\LaravelPayments\WebhookHandlers;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Str;
use Mosaiqo\LaravelPayments\Events\LicenseKeyCreated;
use Mosaiqo\LaravelPayments\Events\LicenseKeyUpdated;
use Mosaiqo\LaravelPayments\Events\OrderCreated;
use Mosaiqo\LaravelPayments\Events\OrderRefunded;
use Mosaiqo\LaravelPayments\Events\SubscriptionCanceled;
use Mosaiqo\LaravelPayments\Events\SubscriptionCreated;
use Mosaiqo\LaravelPayments\Events\SubscriptionExpired;
use Mosaiqo\LaravelPayments\Events\SubscriptionPaused;
use Mosaiqo\LaravelPayments\Events\SubscriptionPaymentFailed;
use Mosaiqo\LaravelPayments\Events\SubscriptionPaymentRecovered;
use Mosaiqo\LaravelPayments\Events\SubscriptionPaymentSuccess;
use Mosaiqo\LaravelPayments\Events\SubscriptionResumed;
use Mosaiqo\LaravelPayments\Events\SubscriptionUnpaused;
use Mosaiqo\LaravelPayments\Events\SubscriptionUpdated;
use Mosaiqo\LaravelPayments\Exceptions\HandleEventMethodNotImplemented;
use Mosaiqo\LaravelPayments\Exceptions\InvalidCustomPayload;
use Mosaiqo\LaravelPayments\Exceptions\InvalidEventName;
use Mosaiqo\LaravelPayments\LaravelPayments;
use Mosaiqo\LaravelPayments\Models\Customer;
use Mosaiqo\LaravelPayments\Models\Subscription;
use Tests\Fixtures\User;
use function DI\string;

class LemonSqueezyWebhookHandler
{
    public function __construct()
    {
    }

    /**
     * Handle the incoming webhook payload.
     *
     * @param array $payload
     * @return void
     * @throws \Mosaiqo\LaravelPayments\Exceptions\HandleEventMethodNotImplemented
     * @throws \Mosaiqo\LaravelPayments\Exceptions\InvalidEventName
     */
    public function handle(array $payload): void
    {
        if (!isset($payload['meta']['event_name'])) {
            throw new InvalidEventName;
        }

        $eventName = $payload['meta']['event_name'];
        $method = 'handle' . Str::studly($eventName) . 'Event';

        if (method_exists($this, $method)) {
            $this->{$method}($payload);
        } else {
            throw new HandleEventMethodNotImplemented('Not implemented');
        }
    }

    /**
     * Handle the order created event.
     * @param $payload
     *
     * @return void
     * @throws \Mosaiqo\LaravelPayments\Exceptions\InvalidCustomPayload
     */
    protected function handleOrderCreatedEvent(array $payload): void
    {
        $customer = $this->resolveBillable($payload);
        $providerId = $payload['data']['id'];
        $attributes = $payload['data']['attributes'];


        $order = $customer->orders()->create([
            'provider_id' => $providerId,
            'provider' => LaravelPayments::PROVIDER_LEMON_SQUEEZY,
            'customer_id' => $attributes['customer_id'],
            'product_id' => (string)$attributes['first_order_item']['product_id'],
            'variant_id' => (string)$attributes['first_order_item']['variant_id'],
            'identifier' => $attributes['identifier'],
            'order_number' => $attributes['order_number'],
            'currency' => $attributes['currency'],
            'subtotal' => $attributes['subtotal'],
            'discount_total' => $attributes['discount_total'],
            'tax' => $attributes['tax'],
            'total' => $attributes['subtotal'] + $attributes['tax'],
            'tax_name' => $attributes['tax_name'],
            'status' => $attributes['status'],
            'receipt_url' => $attributes['urls']['receipt'] ?? null,
            'refunded' => $attributes['refunded'],
            'refunded_at' => $attributes['refunded_at'] ? Carbon::make($attributes['refunded_at']) : null,
            'ordered_at' => Carbon::make($attributes['created_at']),
        ]);

        OrderCreated::dispatch($customer, $order, $payload);
    }

    /**
     * Handle the order refunded event.
     * @param $payload
     *
     * @return void
     * @throws \Mosaiqo\LaravelPayments\Exceptions\InvalidCustomPayload
     */
    protected function handleOrderRefundedEvent(array $payload): void
    {
        $billable = $this->resolveBillable($payload);
        $providerId = $payload['data']['id'];
        $attributes = $payload['data']['attributes'];

        $order = $this->findOrder($providerId);
        if ($order) { $order = $order->sync($attributes); }

        OrderRefunded::dispatch($billable, $order, $payload);
    }

    /**
     * @param $payload
     *
     * @return void
     * @throws \Mosaiqo\LaravelPayments\Exceptions\InvalidCustomPayload
     */
    protected function handleSubscriptionCreatedEvent(array $payload): void
    {
        $customer = $this->resolveBillable($payload);


        $data = $payload['data'] ?? null;
        $custom = $payload['meta']['custom_data'] ?? null;
        $attributes = $payload['data']['attributes'];

        $subscription = $customer->subscriptions()->create([
            'type' => $custom['subscription_type'] ?? Subscription::DEFAULT_TYPE,
            'provider_id' => $data['id'],
            'customer_id' => $attributes['customer_id'],
            'provider' => LaravelPayments::PROVIDER_LEMON_SQUEEZY,
            'status' => $attributes['status'],
            'product_id' => (string) $attributes['product_id'],
            'variant_id' => (string) $attributes['variant_id'],
            'card_brand' => $attributes['card_brand'] ?? null,
            'card_last_four' => $attributes['card_last_four'] ?? null,
            'trial_ends_at' => $attributes['trial_ends_at'] ? Carbon::make($attributes['trial_ends_at']) : null,
            'renews_at' => $attributes['renews_at'] ? Carbon::make($attributes['renews_at']) : null,
            'ends_at' => $attributes['ends_at'] ? Carbon::make($attributes['ends_at']) : null,
        ]);

        // Terminate  the billable's generic trial at the model level if it exists...
        if (!is_null($customer?->trial_ends_at)) {
            $customer->update(['trial_ends_at' => null]);
        }

        // Set the billable's provide id if it was on generic trial at the model level
        if (is_null($customer?->provider_id)) {
            $customer->update([
                'provider_id' => $attributes['customer_id'],
                'provider' => LaravelPayments::PROVIDER_LEMON_SQUEEZY,
            ]);
        }

        SubscriptionCreated::dispatch($customer, $subscription, $payload);
    }

    /**
     * Handle the subscription updated event.
     * @param $payload
     *
     * @return void
     */
    protected function handleSubscriptionUpdatedEvent(array $payload): void
    {
        $providerId = $payload['data']['id'];
        $attributes = $payload['data']['attributes'];

        $subscription = $this->findSubscription($providerId);

        if ($subscription) {
            $subscription->sync($attributes);

            if ($subscription->customer) {
                SubscriptionUpdated::dispatch($subscription->customer, $subscription, $payload);
            }
        }
    }

    /**
     * Handle the subscription canceled event.
     * @param $payload
     *
     * @return void
     */
    protected function handleSubscriptionCanceledEvent(array $payload): void{
        $providerId = $payload['data']['id'];
        $attributes = $payload['data']['attributes'];

        $subscription = $this->findSubscription($providerId);

        if ($subscription) {
            $subscription->sync($attributes);

            if ($subscription->customer) {
                SubscriptionCanceled::dispatch($subscription->customer, $subscription, $payload);
            }
        }
    }

    /**
     * Handle the subscription resumed event.
     * @param $payload
     *
     * @return void
     */
    protected function handleSubscriptionResumedEvent(array $payload): void
    {
        $providerId = $payload['data']['id'];
        $attributes = $payload['data']['attributes'];

        $subscription = $this->findSubscription($providerId);

        if ($subscription) {
            $subscription->sync($attributes);

            if ($subscription->customer) {
                SubscriptionResumed::dispatch($subscription->customer, $subscription, $payload);
            }
        }
    }

    /**
     * Handle the subscription expired event.
     * @param $payload
     *
     * @return void
     */
    protected function handleSubscriptionExpiredEvent(array $payload): void{
        $providerId = $payload['data']['id'];
        $attributes = $payload['data']['attributes'];

        $subscription = $this->findSubscription($providerId);

        if ($subscription) {
            $subscription->sync($attributes);

            if ($subscription->customer) {
                SubscriptionExpired::dispatch($subscription->customer, $subscription, $payload);
            }
        }
    }

    /**
     * Handle the subscription paused event.
     * @param $payload
     *
     * @return void
     */
    protected function handleSubscriptionPausedEvent(array $payload): void{
        $providerId = $payload['data']['id'];
        $attributes = $payload['data']['attributes'];

        $subscription = $this->findSubscription($providerId);

        if ($subscription) {
            $subscription->sync($attributes);

            if ($subscription->customer) {
                SubscriptionPaused::dispatch($subscription->customer, $subscription, $payload);
            }
        }
    }

    /**
     * Handle the subscription unpaused event.
     * @param $payload
     *
     * @return void
     */
    protected function handleSubscriptionUnpausedEvent(array $payload): void{
        $providerId = $payload['data']['id'];
        $attributes = $payload['data']['attributes'];

        $subscription = $this->findSubscription($providerId);

        if ($subscription) {
            $subscription->sync($attributes);

            if ($subscription->customer) {
                SubscriptionUnpaused::dispatch($subscription->customer, $subscription, $payload);
            }
        }
    }


    /**
     * Handle the subscription payment success event.
     *
     * @param array $payload
     *
     * @return void
     */
    protected function handleSubscriptionPaymentSuccessEvent(array $payload): void{
        $attributes = $payload['data']['attributes'];
        $providerId = $attributes['subscription_id'];

        $subscription = $this->findSubscription($providerId);

        if ($subscription && $subscription->customer) {
            SubscriptionPaymentSuccess::dispatch($subscription->customer, $subscription, $payload);
        }
    }


    /**
     * Handle the subscription payment failed event.
     *
     * @param array $payload
     *
     * @return void
     */
    protected function handleSubscriptionPaymentFailedEvent(array $payload): void{
        $attributes = $payload['data']['attributes'];
        $providerId = $attributes['subscription_id'];

        $subscription = $this->findSubscription($providerId);

        if ($subscription && $subscription->customer) {
            SubscriptionPaymentFailed::dispatch($subscription->customer, $subscription, $payload);
        }
    }


    /**
     * Handle the subscription payment failed event.
     *
     * @param array $payload
     *
     * @return void
     */
    protected function handleSubscriptionPaymentRecoveredEvent(array $payload): void{
        $attributes = $payload['data']['attributes'];
        $providerId = $attributes['subscription_id'];

        $subscription = $this->findSubscription($providerId);

        if ($subscription && $subscription->customer) {
            SubscriptionPaymentRecovered::dispatch($subscription->customer, $subscription, $payload);
        }
    }


    /**
     * Handle the license key created event.
     *
     * @param array $payload
     *
     * @return void
     * @throws \Mosaiqo\LaravelPayments\Exceptions\InvalidCustomPayload
     */
    protected function handleLicenseKeyCreatedEvent(array $payload): void{
        $billable = $this->resolveBillable($payload);

        LicenseKeyCreated::dispatch($billable, $payload);
    }


    /**
     * Handle the license key updated event.
     *
     * @param array $payload
     *
     * @return void
     * @throws \Mosaiqo\LaravelPayments\Exceptions\InvalidCustomPayload
     */
    protected function handleLicenseKeyUpdatedEvent(array $payload): void{
        $billable = $this->resolveBillable($payload);

        LicenseKeyUpdated::dispatch($billable, $payload);
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
        $custom = $payload['meta']['custom_data'] ?? null;

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
            (string) $payload['data']['attributes']['customer_id'],
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
        $attributes = $payload['data']['attributes'];
        $customer = null;
        if($billableId) {
            $customer = $model::firstOrCreate([
                'billable_id' => $billableId,
                'billable_type' => Relation::getMorphAlias($billableType),
            ], [
                'provider_id' => $customerId,
                'provider' => LaravelPayments::PROVIDER_LEMON_SQUEEZY,
                'name' => $attributes['user_name'],
                'email' => $attributes['user_email'],
            ]);
        }

        if (!$customer) {
            $customer = $model::firstOrCreate([
                'provider_id' => $customerId,
                'provider' => LaravelPayments::PROVIDER_LEMON_SQUEEZY,
                'billable_id' => $billableId,
                'billable_type' => Relation::getMorphAlias($billableType),
            ], [
                'name' => $attributes['user_name'],
                'email' => $attributes['user_email'],
            ]);
        }

        if(!$customer->name || !$customer->email){
            $customer->update([
                'name' => $attributes['user_name'],
                'email' => $attributes['user_email'],
            ]);
        }

        return $customer ?? null;
    }

    /**
     * Find an order by the provider id.
     *
     * @param string $providerId
     * @return ?Model
     */
    private function findOrder(string $providerId): ?Model
    {
        $model = LaravelPayments::resolveOrderModel();
        return $model::firstWhere('provider_id', $providerId);
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
