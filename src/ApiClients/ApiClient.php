<?php

namespace Mosaiqo\LaravelPayments\ApiClients;

use Illuminate\Support\Str;
use Mosaiqo\LaravelPayments\Exceptions\InvalidProvider;
use Mosaiqo\LaravelPayments\LaravelPayments;

class ApiClient
{
    protected function __construct() {}

    protected function clients() {
        return [
            LaravelPayments::PROVIDER_LEMON_SQUEEZY => LemonSqueezyApiClient::class,
            LaravelPayments::PROVIDER_STRIPE => StripeApiClient::class
        ];
    }

    protected function providerHasClient($provider) {
        return array_key_exists($provider, $this->clients());
    }

    public function resolveProviderConfig($provider)
    {
        return config('payments.providers.' . $provider, []);
    }


    public function resolveProviderClient($provider)
    {
        return $this->clients()[$provider];
    }

    public static function forProvider($provider)
    {
        $instance = new self();

        if(!$instance->providerHasClient($provider)) {
            throw new InvalidProvider($provider);
        }

        $config = $instance->resolveProviderConfig($provider);
        $client = $instance->resolveProviderClient($provider);

        return new $client($config['api_key']);
    }


    public static function make()
    {
        LaravelPayments::checkProviderIsConfigured();
        return self::forProvider(LaravelPayments::getProvider());
    }
}
