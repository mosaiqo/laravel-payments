<?php

namespace Mosaiqo\LaravelPayments;

use Illuminate\Support\Facades\Cache;
use Laravel\Cashier\Cashier;
use Mosaiqo\LaravelPayments\Services\LemonSqueezyService;

class LemonSqueezyPlanMapper
{
    public static bool $cache = false;

    protected static function slug($name)
    {
        return match ($name) {
            'Free' => 'free',
            'Pro' => 'pro',
            'Premium' => 'premium',
            default => config('plans.default')
        };
    }

    protected static function featured($name)
    {
        return match ($name) {
            'Pro' => true,
            default => false
        };
    }

    protected static function discountCode($key)
    {
        return match ($key) {
            'year' => 'YEARLYDISCOUNT',
            default => null
        };
    }

    public static function map(array $plan): array
    {
        $plan = (object)$plan;
        $slug = self::slug($plan->name);
        $product = (object)config("plans.products.{$slug}");
        $discounted_price = $plan->price - ($plan->price * 0.17);
        return [
            'id' => $plan->id,
            'name' => $plan->name,
            'description' => $plan->description,
            'price' => $plan->price,
            'formated_price' => Cashier::formatAmount($plan->price),
            'discounted_price' => $discounted_price,
            'formated_discounted_price' => Cashier::formatAmount($discounted_price),
            "featured" => self::featured($plan->name),
            "intervals" => self::intervals($plan),
            "features" => $product->features ?? [],
            "features_compare" => $product->features_compare ?? false,
            "href" => $plan->buy_now_url,
            "sort_order" => $product->sort_order,
            "cta" => $product->cta ?? null,
            "cta_message" => $product->cta_message ?? null,
        ];
    }

    protected static function intervals($plan)
    {
        return collect($plan->variants)
            ->mapWithKeys(function ($variant) use ($plan) {
                $key = IntervalKeyMapper::map($variant['interval'], $variant['interval_count']);
                $checkout = self::$cache ? self::getCachedCheckout($variant, $key) ?? null : self::getCheckout($variant, $key);
                $checkout = (object) $checkout;
                $variant['price_formatted'] = Cashier::formatAmount($variant['price']);
                $variant['discounted_price'] = $checkout->has_discount ? $checkout?->total : null;
                $variant['discounted_price_formatted'] = $checkout->has_discount ? Cashier::formatAmount($variant['discounted_price']) : null;
                $variant['href'] = $checkout?->url;
                return [$key => $variant];
            });
    }

    protected static function getCachedCheckout($variant, $key)
    {
        $provider = LaravelPayments::PROVIDER_LEMON_SQUEEZY;
        $variantId = $variant['id'];
        return Cache::rememberForever(
            "{$provider}.checkout.{$variantId}.{$key}",
            static fn () => static::getCheckout($variant, $key)
        );
    }

    protected static function getCheckout($variant, $key)
    {
        $variantId = $variant['id'];
        $price = $variant['price'] ?? 0;
        $discountCode = $price > 0 ? self::discountCode($key) : null;
        $checkout = (object) (new LemonSqueezyService)->checkout($variantId, $discountCode);
        if (!$checkout || !$checkout?->preview) {
            return (object) [
                'id' => null,
                'url' => null,
                'has_discount' => false,
            ];
        }

        return(object) array_merge(
            $checkout->preview, [
            'url' => route("payments.checkouts", [
                "product" => $variant['product_id'],
                "variant" => $variant['id'],
                "discount" => $discountCode
            ]),
//            'url' => $checkout->url,
            'has_discount' => $checkout->preview['discount_total'] > 0,
        ]);
    }

}
