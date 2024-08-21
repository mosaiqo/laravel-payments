<?php

namespace Mosaiqo\LaravelPayments\Exceptions;

use Exception;

class MissingApiKey extends Exception
{
    public static function lemonSqueezy(): static
    {
        return new static('You must provide a valid LemonSqueezy API key to make requests.');
    }

    public static function stripe(): static
    {
        return new static('You must provide a valid Stripe API key to make requests.');
    }

}
