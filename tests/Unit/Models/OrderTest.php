<?php

use Mosaiqo\LaravelPayments\Models\Order;
use Tests\TestCase;

uses(TestCase::class);

it('can determine if the order is pending', function () {
    $subscription = new Order();
    expect($subscription->pending())->toBeFalse();
    $subscription->status = Order::STATUS_PENDING;

    expect($subscription->pending())->toBeTrue();
});

it('can determine if the order is paid', function () {
    $subscription = new Order();
    expect($subscription->paid())->toBeFalse();
    $subscription->status = Order::STATUS_PAID;

    expect($subscription->paid())->toBeTrue();
});

it('can determine if the order is failed', function () {
    $subscription = new Order();
    expect($subscription->failed())->toBeFalse();
    $subscription->status = Order::STATUS_FAILED;

    expect($subscription->failed())->toBeTrue();
});


it('can determine if the order is refunded', function () {
    $subscription = new Order();
    expect($subscription->refunded())->toBeFalse();
    $subscription->status = Order::STATUS_REFUNDED;

    expect($subscription->refunded())->toBeTrue();
});


it('can determine if the order is for a specific product', function () {
    $subscription = new Order();
    expect($subscription->hasProduct('product_123'))->toBeFalse();
    $subscription->product_id = 'product_123';

    expect($subscription->hasProduct('product_123'))->toBeTrue();
});

it('can determine if the order is for a specific variant', function () {
    $subscription = new Order();
    expect($subscription->hasVariant('variant_123'))->toBeFalse();
    $subscription->variant_id = 'variant_123';

    expect($subscription->hasVariant('variant_123'))->toBeTrue();
});


