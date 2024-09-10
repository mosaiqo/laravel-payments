<?php

namespace Mosaiqo\LaravelPayments;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Facade;
use Laravel\Cashier\Cashier;

class PlanMapper extends Facade
{
    protected bool $cache = false;

    protected string|null $provider = null;

    protected string|null $mapper = null;

    protected array $mappers = [
        LaravelPayments::PROVIDER_LEMON_SQUEEZY => LemonSqueezyPlanMapper::class,
        LaravelPayments::PROVIDER_STRIPE => StripePlanMapper::class,
    ];

    public function withCache($active = true)
    {
        $this->cache = $active;
        return $this;
    }

    public function forProvider(string $provider): self
    {
        $this->provider = $provider;
        return $this;
    }

    public function withMapper(string $mapper)
    {
        $this->mapper = $mapper;
        return $this;
    }

    /**
     * @throws \Exception
     */
    public function map(Collection $plans): Collection
    {
        $this->setMapper();
        return $plans->map(fn($plan) => $this->mapper::map($plan));
    }

    protected function setMapper(): static
    {
        if (!$this->provider) {
            throw new \Exception('Provider not set');
        }

        if (!isset($this->mappers[$this->provider])) {
            throw new \Exception('Mapper not found');
        }

        if (!$this->mapper) {
            $this->mapper = $this->mappers[$this->provider];
        }


        return $this;
    }

    public function get()
    {
        if (!$this->provider) {
          $this->forProvider(config('payments.provider'));
        }

        $this->setMapper();

        if ($this->cache && Cache::has("{$this->provider}-plans")) {
            return Cache::get("{$this->provider}-plans");
        } else {
            $plans = $this->map(PaymentsService::products())
                ->sort(fn($a, $b) => $a['sort_order'] <=> $b['sort_order'])
                ->values();
            if ($this->cache) {
                Cache::put("{$this->provider}-plans", $plans);
            }

            return $plans;
        }
    }

    public function byProductId($productId)
    {
        return $this->get()->firstWhere('id', $productId) ?? [];
    }

    public function bySlug($slug)
    {
        return $this->get()->firstWhere('slug', $slug) ?? [];
    }
}
