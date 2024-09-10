<?php

namespace Mosaiqo\LaravelPayments\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Mosaiqo\LaravelPayments\Models\Customer;
use Mosaiqo\LaravelPayments\Models\Order;

class OrderCreated
{
    use Dispatchable, SerializesModels;

    /**
     * The customer entity.
     */
    public Customer $customer;

    /**
     * The order entity.
     *
     */
    public Order $order;

    /**
     * The payload array.
     */
    public array $payload;

    public function __construct(Customer $customer, Order $order, array $payload)
    {
        $this->customer = $customer;
        $this->order = $order;
        $this->payload = $payload;
    }
}
