<?php

namespace  Mosaiqo\LaravelPayments\Database\Factories;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\Factory;
use Mosaiqo\LaravelPayments\LaravelPayments;
use Mosaiqo\LaravelPayments\Models\Subscription;
use Mosaiqo\LaravelPayments\Models\SubscriptionItem;

class SubscriptionItemFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = SubscriptionItem::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $provider = LaravelPayments::getProvider() ?? $this->faker->randomElement(LaravelPayments::allowedProviders());
        return [
            'payments_subscription_id' => rand(1, 1000),
            'provider' => $provider,
            'provider_id' => rand(1, 1000),
            'provider_subscription_id' => rand(1, 1000),
            'provider_product_id' => rand(1, 1000),
            'provider_price_id' => rand(1, 1000),
            'is_usage_based' => $this->faker->boolean(),
        ];
    }

    /**
     * Configure the model factory.
     */
    public function configure(): self
    {
        return $this->afterCreating(function ($item) {
            SubscriptionFactory::new([
                'provider' => $item->provider,
            ])->create();
        });
    }
}
