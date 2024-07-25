<?php

namespace Mosaiqo\LaravelPayments\Concerns;

use Mosaiqo\LaravelPayments\ApiClients\LemonSqueezyApiClient;
use Mosaiqo\LaravelPayments\LaravelPayments;
use Mosaiqo\LaravelPayments\Models\Customer;

trait ManagesCustomer
{
    /**
     * Get the customer related to the billable model.
     */
    public function customer(): \Illuminate\Database\Eloquent\Relations\MorphOne
    {
        $model = LaravelPayments::resolveCustomerModel();

        return $this->morphOne($model, 'billable');
    }

    /**
     * Create a customer record for the billable model.
     */
    public function createAsCustomer(array $attributes = []): Customer
    {
        LaravelPayments::checkProviderIsConfigured();
        return $this->customer()->create($attributes);
    }

    public function customerPortalUrl(): string
    {
        $response = LaravelPayments::api()->getCustomer($this->customer->provider_id);

        return $response['data']['attributes']['urls']['customer_portal'];
    }

    /**
     * Get the billable's name to associate with Provider.
     */
    public function customerName(): ?string
    {
        return $this->name ?? null;
    }

    /**
     * Get the billable's email to associate with Provider.
     */
    public function customerEmail(): ?string
    {
        return $this->email ?? null;
    }

    /**
     * Get the billable's country to associate with Provider.
     */
    public function customerCountry(): ?string
    {
        return $this->country ?? null;
    }

    /**
     * Get the billable's zip to associate with Provider.
     */
    public function customerZip(): ?string
    {
        return $this->zip ?? null;
    }

    /**
     * Get the billable's tax_number to associate with Provider.
     */
    public function customerTaxNumber(): ?string
    {
        return $this->tax_number ?? null;
    }

}
