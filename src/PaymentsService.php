<?php

namespace Mosaiqo\LaravelPayments;

use Illuminate\Database\Eloquent\Model;
use Mosaiqo\LaravelPayments\Services\Contracts\PaymentsServiceProvider;
use Illuminate\Support\Facades\Cache;
use Mosaiqo\LaravelPayments\Services\LemonSqueezyService;
use Mosaiqo\LaravelPayments\Services\StripeService;

class PaymentsService
{
    /**
     * @var \Mosaiqo\LaravelPayments\LemonSqueezyService
     */
    protected PaymentsServiceProvider $client;

    public ?string $provider;

    public function __construct()
    {
        $this->provider = LaravelPayments::getProvider();
        $this->client = $this->getClient($this->provider);
    }

    public static function checkout($variant, $discountCode = null, ?Model $billable = null)
    {
        return (new static())->client->checkout($variant, $discountCode, $billable);
    }


    public static function products($cache = false)
    {
        $instance = new static();
        if (!$cache) return $instance->getProducts();
        return Cache::rememberForever(
            "{$instance->provider}-products",
            static fn() => $instance->getProducts()
        );
    }

    protected function getProducts()
    {
        return $this->client->products();
    }


    protected function getClient($provider = null): PaymentsServiceProvider
    {
        return match ($provider) {
            LaravelPayments::PROVIDER_LEMON_SQUEEZY => new LemonSqueezyService(),
            LaravelPayments::PROVIDER_STRIPE => new StripeService(),
            default => throw new \Exception('Provider not found'),
        };
    }
}
