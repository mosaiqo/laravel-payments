<?php

namespace Mosaiqo\LaravelPayments\Concerns;

use Mosaiqo\LaravelPayments\Checkout;
use Mosaiqo\LaravelPayments\Exceptions\MissingStore;
use Mosaiqo\LaravelPayments\LaravelPayments;

trait ManagesCheckouts
{
    /**
     * @throws \Mosaiqo\LaravelPayments\Exceptions\MissingStore
     * @throws \Mosaiqo\LaravelPayments\Exceptions\ReservedCustomKeys
     */
    public function checkout(string $variant, array $options = [], array $custom = [])
    {
        // We'll need a way to identify the user in any webhook we're catching so before
        // we make an API request we'll attach the authentication identifier to this
        // checkout so we can match it back to a user when handling webhooks.
        $custom = array_merge($custom, [
            'billable_id' => (string) $this->getKey(),
            'billable_type' => $this->getMorphClass(),
        ]);

        $storeId = $this->resolveStoreId();
        return Checkout::make($storeId, $variant)
            ->withName($options['name'] ?? (string) $this->customerName())
            ->withEmail($options['email'] ?? (string) $this->customerEmail())
            ->withBillingAddress(
                $options['country'] ?? (string) $this->customerCountry(),
                $options['zip'] ?? (string) $this->customerZip(),
            )
            ->withTaxNumber($options['tax_number'] ?? (string) $this->customerTaxNumber())
            ->withDiscountCode($options['discount_code'] ?? '')
            ->withCustomPrice($options['custom_price'] ?? null)
            ->withCustomData($custom);
    }


    /**
     * @throws \Mosaiqo\LaravelPayments\Exceptions\MissingStore
     */
    protected function resolveStoreId()
    {
        $store = config('payments.providers.lemon-squeezy.store');

        if (! $store) {
            throw MissingStore::notConfigured();
        }

        return $store;
    }
}
