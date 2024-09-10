<?php

namespace  Mosaiqo\LaravelPayments\Database\Factories;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\Factory;
use Mosaiqo\LaravelPayments\LaravelPayments;
use Mosaiqo\LaravelPayments\Models\Customer;
use  Mosaiqo\LaravelPayments\Models\Subscription;
use Mosaiqo\LaravelPayments\Models\SubscriptionItem;

class SubscriptionFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Subscription::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $provider = LaravelPayments::getProvider() ?? $this->faker->randomElement(LaravelPayments::allowedProviders());
        return [
            'type' => Subscription::DEFAULT_TYPE,
            'payments_customer_id' => Customer::factory(),
            'customer_id' => rand(1, 1000),
            'provider_id' => rand(1, 1000),
            'provider' => $provider,
            'status' => Subscription::STATUS_ACTIVE,
            'product_id' => rand(1, 1000),
            'variant_id' => rand(1, 1000),
            'card_brand' => $this->faker->randomElement(['visa', 'mastercard', 'american_express', 'discover', 'jcb', 'diners_club']),
            'card_last_four' => rand(1000, 9999),
            'pause_mode' => null,
            'pause_resumes_at' => null,
            'trial_ends_at' => null,
            'renews_at' => null,
            'ends_at' => null,
        ];
    }


    /**
     * Mark the subscription as being within a trial period.
     */
    public function trialing(?DateTimeInterface $trialEndsAt = null): self
    {
        return $this->state([
            'status' => Subscription::STATUS_ON_TRIAL,
            'trial_ends_at' => $trialEndsAt,
        ]);
    }

    /**
     * Mark the subscription as active.
     */
    public function active(): self
    {
        return $this->state([
            'status' => Subscription::STATUS_ACTIVE,
        ]);
    }

    /**
     * Mark the subscription as paused.
     */
    public function paused(?DateTimeInterface $resumesAt = null): self
    {
        return $this->state([
            'status' => Subscription::STATUS_PAUSED,
            'pause_mode' => $this->faker->randomElement(['void', 'free']),
            'pause_resumes_at' => $resumesAt,
        ]);
    }

    /**
     * Mark the subscription as past due.
     */
    public function pastDue(): self
    {
        return $this->state([
            'status' => Subscription::STATUS_PAST_DUE,
        ]);
    }

    /**
     * Mark the subscription as unpaid.
     */
    public function unpaid(): self
    {
        return $this->state([
            'status' => Subscription::STATUS_UNPAID,
        ]);
    }

    /**
     * Mark the subscription as canceled.
     */
    public function canceled(): self
    {
        return $this->state([
            'status' => Subscription::STATUS_CANCELED,
            'ends_at' => now(),
        ]);
    }

    /**
     * Mark the subscription as expired
     */
    public function expired(): self
    {
        return $this->state([
            'status' => Subscription::STATUS_EXPIRED,
        ]);
    }
}
