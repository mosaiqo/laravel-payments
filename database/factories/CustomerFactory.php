<?php

namespace Mosaiqo\LaravelPayments\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Mosaiqo\LaravelPayments\LaravelPayments;
use  Mosaiqo\LaravelPayments\Models\Customer;

class CustomerFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Customer::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $provider = LaravelPayments::getProvider() ?? $this->faker->randomElement(LaravelPayments::allowedProviders());
        return [
            'billable_id' => rand(1, 1000),
            'billable_type' => LaravelPayments::resolveBillableModel() ?? 'App\\Models\\User',
            'provider_id' => rand(1, 1000),
            'provider' => $provider,
            'trial_ends_at' => null,
        ];
    }
}
