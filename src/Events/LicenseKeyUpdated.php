<?php

namespace Mosaiqo\LaravelPayments\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Mosaiqo\LaravelPayments\Models\Customer;

class LicenseKeyUpdated
{
    use Dispatchable, SerializesModels;

    /**
     * The billable entity.
     */
    public Customer $customer;

    /**
     * The payload array.
     */
    public array $payload;

    public function __construct(Customer $customer, array $payload)
    {
        $this->customer = $customer;
        $this->payload = $payload;
    }
}
