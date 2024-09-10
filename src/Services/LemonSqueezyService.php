<?php

namespace Mosaiqo\LaravelPayments\Services;

use Illuminate\Database\Eloquent\Model;
use Mosaiqo\LaravelPayments\ApiClients\ApiClient;
use Mosaiqo\LaravelPayments\Services\Contracts\PaymentsServiceProvider;
use Mosaiqo\LaravelPayments\Checkout;
use Mosaiqo\LaravelPayments\LaravelPayments;

class LemonSqueezyService implements PaymentsServiceProvider
{
    private $client;
    private mixed $store;

    public function __construct()
    {
        $this->client = ApiClient::forProvider(LaravelPayments::PROVIDER_LEMON_SQUEEZY);
        $this->store = LaravelPayments::resolveProviderConfig()['store'];
    }

    public function checkout($variant, $discountCode = null, ?Model $billable = null) {
        $options = [
          'discount_code' => $discountCode,
        ];

        if ($billable) {
            $checkout = $billable->checkout($variant, $options);
        } else {
            $checkout = (new Checkout($this->store, $variant))
                ->withDiscountCode($options['discount_code'] ?? '');
        }

        return (object) $checkout->withoutVariants()->attributes();
    }

    public function getCustomerPortalLink($id)
    {
        return $this->client->getCustomer($id)->json('data.attributes.urls.customer_portal');
    }

    public function products()
    {
        $storeId = $this->store;

        $response = $this->client
            ->get("stores/{$storeId}/products", [
                'include' => 'variants',
                'page[size]' => 100,
            ]);

        $data = $response->json('data');
        $included = $response->json('included');

        $variants = collect($included)
            ->filter(fn ($item) => $item['type'] === 'variants')
            ->map(function ($variant) {
                $variant['attributes']['id'] = $variant['id'];
                return $variant;
            })
            ->pluck('attributes');

        return collect($data)
            ->filter(fn ($product) => $product['attributes']['status'] === 'published')
            ->map(function ($product) use ($variants) {
                $productId = $product['id'];
                $product = array_merge($product['attributes'], ['id' => $productId]);
                $product['variants'] = $variants->where('product_id', $product['id'])->all();
                return $product;
            });
    }

    public function product($productId, $variantId = null)
    {
        $storeId = $this->store;

        $response = $this->client
            ->get("products/{$productId}", [
                'include' => 'variants'
            ]);

        $data = $response->json('data');
        $included = $response->json('included');

        $variants = collect($included)
            ->filter(fn ($item) => $item['type'] === 'variants')
            ->filter(fn ($item) => !$variantId || $item['id'] === $variantId)
            ->map(function ($variant) {
                $variant['attributes']['id'] = $variant['id'];
                return $variant;
            })
            ->pluck('attributes');



//        dd($variants, $data);
        $product = collect([$data])
            ->filter(fn ($product) => $product['attributes']['status'] === 'published')
            ->map(function ($product) use ($variants) {
                $productId = $product['id'];
                $product = array_merge($product['attributes'], ['id' => $productId]);
                $product['variants'] = $variants->where('product_id', $product['id'])->all();
                return $product;
            })->first();
        dd($product);
    }
}
