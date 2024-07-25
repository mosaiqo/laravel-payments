<?php

use Mosaiqo\LaravelPayments\Models\Subscription;
use Tests\TestCase;

uses(TestCase::class);

it('can determine if the subscription is active', function () {
    $subscription = new Subscription();
    expect($subscription->active())->toBeFalse();
    $subscription->status = Subscription::STATUS_ACTIVE;

    expect($subscription->active())->toBeTrue();
});


it('can determine if the subscription is on trial', function () {
    $subscription = new Subscription();
    expect($subscription->onTrial())->toBeFalse();
    $subscription->status = Subscription::STATUS_ON_TRIAL;

    expect($subscription->onTrial())->toBeTrue();
});

it('can determine if the subscription is past due', function () {
    $subscription = new Subscription();
    expect($subscription->pastDue())->toBeFalse();
    $subscription->status = Subscription::STATUS_PAST_DUE;

    expect($subscription->pastDue())->toBeTrue();
});

it('can determine if the subscription is cancelled', function () {
    $subscription = new Subscription();
    expect($subscription->cancelled())->toBeFalse();
    $subscription->status = Subscription::STATUS_CANCELLED;

    expect($subscription->cancelled())->toBeTrue();
});

it('can determine if the subscription is paused', function () {
    $subscription = new Subscription();
    expect($subscription->paused())->toBeFalse();
    $subscription->status = Subscription::STATUS_PAUSED;

    expect($subscription->paused())->toBeTrue();
});


it('can determine if the subscription is unpaid', function () {
    $subscription = new Subscription();
    expect($subscription->unpaid())->toBeFalse();
    $subscription->status = Subscription::STATUS_UNPAID;

    expect($subscription->unpaid())->toBeTrue();
});

it('can determine if the subscription is expired', function () {
    $subscription = new Subscription();
    expect($subscription->expired())->toBeFalse();
    $subscription->status = Subscription::STATUS_EXPIRED;
    expect($subscription->expired())->toBeTrue();
});


it('can determine if the subscription has product', function () {
    $subscription = new Subscription();
    expect($subscription->hasProduct('1'))->toBeFalse();

    $subscription->product_id = '1';

    expect($subscription->hasProduct('1'))->toBeTrue();
});


it('can determine if the subscription has variant', function () {
    $subscription = new Subscription();
    expect($subscription->hasVariant('variant_id'))->toBeFalse();
    $subscription->variant_id = 'variant_id';

    expect($subscription->hasVariant('variant_id'))->toBeTrue();
});


it('can determine if the subscription has expired trial', function () {
    $subscription = new Subscription();
    expect($subscription->hasExpiredTrial())->toBeFalse();
    $subscription->trial_ends_at = now()->subDay();

    expect($subscription->hasExpiredTrial())->toBeTrue();
});


it('can determine if the subscription is valid', function () {
    $subscription = new Subscription();
    $subscription->status = Subscription::STATUS_ACTIVE;

    expect($subscription->valid())->toBeTrue();
});
