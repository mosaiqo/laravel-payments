<?php

namespace Mosaiqo\LaravelPayments\Exceptions;

use Exception;

class MissingProvider extends Exception
{
    public static function notConfigured(): static
    {
        return new static('No payments provider was configured.');
    }

    public static function notSupported(string $provider, array $allowed = []): static
    {
        $allowed = implode(', ', $allowed);
        return new static("The payment provider [{$provider}] is not supported. Allowed providers are: [{$allowed}]");
    }
}
