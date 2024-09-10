<?php

namespace Mosaiqo\LaravelPayments\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Mosaiqo\LaravelPayments\Models\Customer;
use Mosaiqo\LaravelPayments\Models\Order;

class OrderRefunded
{
    use Dispatchable, SerializesModels;

    /**
     * The customer entity.
     */
    public Customer $customer;

    /**
     * The order entity.
     *
     * @todo v2: Remove the nullable type hint.
     */
    public ?Order $order;

    /**
     * The payload array.
     */
    public array $payload;

    public function __construct(Customer $customer, ?Order $order, array $payload)
    {
        $this->customer = $customer;
        $this->order = $order;
        $this->payload = $payload;
    }
}
