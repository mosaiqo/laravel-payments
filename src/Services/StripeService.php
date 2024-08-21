<?php

namespace Mosaiqo\LaravelPayments\Services;

use Mosaiqo\LaravelPayments\ApiClients\ApiClient;
use Mosaiqo\LaravelPayments\ApiClients\StripeApiClient;
use Mosaiqo\LaravelPayments\LaravelPayments;
use Mosaiqo\LaravelPayments\Services\Contracts\PaymentsServiceProvider;

class StripeService implements PaymentsServiceProvider
{
    private StripeApiClient $client;

    public function __construct($options = [])
    {
        $this->client = ApiClient::forProvider(LaravelPayments::PROVIDER_STRIPE);
    }

    public function checkout($variant, $discountCode = null) {
        return $this->client->checkout($variant, $discountCode);
    }

    public function products()
    {
        $productsResponse = $this->client->products->all(['expand' => ['data.default_price'],'limit' => 100]);
        $pricesResponse = $this->client->prices->all(['limit' => 100]);
        $prices = collect($pricesResponse->data);
        $products = collect($productsResponse->data);

        return $products
            ->filter(fn ($product) => $product->active)
            ->filter(fn ($product) => $product->default_price->type === 'recurring')
            ->map(function ($product) use ($prices) {
                $product->variants = $prices->filter(function ($price) use($product) {
                    return $price->product == $product->id;
                });

                return $product;
        });
    }
}
