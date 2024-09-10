<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Mosaiqo\LaravelPayments\Database\Factories\OrderFactory;
use Mosaiqo\LaravelPayments\LaravelPayments;
use Mosaiqo\LaravelPayments\Models\Order;

uses(RefreshDatabase::class);

it('returns correct orders when scoped', function () {
    config()->set([
        'payments.provider' => LaravelPayments::PROVIDER_LEMON_SQUEEZY,
        'payments.providers.lemon-squeezy.store' => 'store_12345',
        'payments.providers.lemon-squeezy.api_key' => 'fake_key',
    ]);

    Order::factory()->createMany([
        ['status' => Order::STATUS_PAID],
        ['status' => Order::STATUS_FAILED],
        ['status' => Order::STATUS_PENDING],
        ['status' => Order::STATUS_REFUNDED],
    ]);

    $items = Order::query()->pending()->get();
    expect($items->count())->toBe(1);
    $items->each(function ($order) {
        expect($order->status)->toBe(Order::STATUS_PENDING);
    });

    $items = Order::query()->failed()->get();
    expect($items->count())->toBe(1);
    $items->each(function ($order) {
        expect($order->status)->toBe(Order::STATUS_FAILED);
    });

    $items = Order::query()->paid()->get();
    expect($items->count())->toBe(1);
    $items->each(function ($order) {
        expect($order->status)->toBe(Order::STATUS_PAID);
    });

    $items = Order::query()->refunded()->get();
    expect($items->count())->toBe(1);
    $items->each(function ($order) {
        expect($order->status)->toBe(Order::STATUS_REFUNDED);
    });
});

it('returns billable from order', function () {
    config()->set([
        'payments.provider' => LaravelPayments::PROVIDER_LEMON_SQUEEZY,
        'payments.providers.lemon-squeezy.store' => 'store_12345',
        'payments.providers.lemon-squeezy.api_key' => 'fake_key',
    ]);

    $user = \Tests\Fixtures\User::factory()->create();
    $customer = $user->createAsCustomer([
        'provider' => LaravelPayments::PROVIDER_LEMON_SQUEEZY,
        'provider_id' => 'cus_123',
        'email' => 'johndoe@email.com',
        'name' => 'John Doe',
    ]);
    $order = Order::factory()->create([
        'payments_customer_id' => $customer->id
    ]);

    expect($order->billable)->not()->toBeNull();
});
