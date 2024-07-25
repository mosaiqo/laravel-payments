<?php

use Mosaiqo\LaravelPayments\Models\Customer;
use Tests\TestCase;

uses(TestCase::class);

it('can determine if the customer is on a generic trial', function () {
    $customer = Customer::newFactory()->make();
    $customer->trial_ends_at = now()->addDays(7);

    expect($customer->onGenericTrial())->toBeTrue();
});


it('can determine if the customer is has expired generic trial', function () {
    $customer = new Customer();
    $customer->trial_ends_at = now()->subDays(7);

    expect($customer->hasExpiredGenericTrial())->toBeTrue();
});
