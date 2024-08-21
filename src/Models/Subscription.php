<?php

namespace Mosaiqo\LaravelPayments\Models;

use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Mosaiqo\LaravelPayments\Models\Concerns\Prorates;
use Mosaiqo\LaravelPayments\LaravelPayments;

use Mosaiqo\LaravelPayments\Database\Factories\SubscriptionFactory;

/**
 * @property ?string                                  $provider_id
 * @property ?string                                  $status
 * @property boolean                                  $pause_mode
 * @property ?string                                  $product_id
 * @property string                                   $provider_price_id
 * @property \Illuminate\Database\Eloquent\Collection $items
 */
class Subscription extends Model
{
    use HasFactory;
    use Prorates;

    const STATUS_ON_TRIAL = 'on_trial';

    const STATUS_ACTIVE = 'active';

    const STATUS_PAUSED = 'paused';

    const STATUS_PAST_DUE = 'past_due';

    const STATUS_UNPAID = 'unpaid';

    const STATUS_CANCELED = 'canceled';

    const STATUS_EXPIRED = 'expired';

    const DEFAULT_TYPE = 'default';

    protected static string $factory = SubscriptionFactory::class;

    protected $table = 'payments_subscriptions';

    /**
     * The attributes that are not mass assignable.
     *
     * @var array
     */
    protected $guarded = [];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'pause_resumes_at' => 'datetime',
        'trial_ends_at' => 'datetime',
        'renews_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    /**
     * Get the billable model related to the subscription.
     */
    public function billable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the items for the subscription.
     */
    public function items(): HasMany
    {
        return $this->hasMany(SubscriptionItem::class);
    }

    /**
     * Filter query by on trial.
     */
    public function scopeOnTrial(Builder $query): void
    {
        $query->where('status', self::STATUS_ON_TRIAL);
    }

    /**
     * Filter query by on active.
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * Filter query by paused.
     */
    public function scopePaused(Builder $query): void
    {
        $query->where('status', self::STATUS_PAUSED);
    }

    /**
     * Filter query by paused.
     */
    public function scopePastDue(Builder $query): void
    {
        $query->where('status', self::STATUS_PAST_DUE);
    }

    /**
     * Filter query by unpaid.
     */
    public function scopeUnpaid(Builder $query): void
    {
        $query->where('status', self::STATUS_UNPAID);
    }

    /**
     * Filter query by canceled.
     */
    public function scopeCanceled(Builder $query): void
    {
        $query->where('status', self::STATUS_CANCELED);
    }

    /**
     * Filter query by expired.
     */
    public function scopeExpired(Builder $query): void
    {
        $query->where('status', self::STATUS_EXPIRED);
    }

    /**
     * Filter query by expired.
     */
    public function onGracePeriod(): bool
    {
        return $this->canceled() && $this->ends_at?->isFuture();
    }

    /**
     * Determine if the subscription is within its paused period.
     */
    public function onPausedPeriod(): bool
    {
        return $this->paused() && $this->pause_resumes_at?->isFuture();
    }

    /**
     * Change the billing cycle anchor on the subscription.
     */
    public function anchorBillingCycleOn(?int $date): self
    {
        $response = LaravelPayments::api()->anchorSubscriptionBillingCycleOn($this->provider_id, $date);

        $this->sync($response['data']['attributes']);

        return $this;
    }

    public function endTrial(): self
    {
        return $this->anchorBillingCycleOn(0);
    }


    public function cancel(): self
    {
        $response = LaravelPayments::api()->cancelSubscription($this->provider_id);

        $this->sync($response['data']['attributes']);
        return $this;
    }

    public function resume(): self
    {
        if ($this->expired()) {
            throw new \LogicException('Cannot resume an expired subscription.');
        }

        $response = LaravelPayments::api()->resumeSubscription($this->provider_id);

        $this->sync($response['data']['attributes']);
        return $this;
    }

    public function pause(?DateTimeInterface $resumesAt = null): self
    {
        $response = LaravelPayments::api()->pauseSubscription($this->provider_id, 'void', $resumesAt);

        $this->sync($response['data']['attributes']);
        return $this;
    }

    public function pauseForFree(?DateTimeInterface $resumesAt = null): self
    {
        $response = LaravelPayments::api()->pauseSubscription($this->provider_id, 'free', $resumesAt);

        $this->sync($response['data']['attributes']);
        return $this;
    }

    public function unpause(): self
    {
        $response = LaravelPayments::api()->unpauseSubscription($this->provider_id);

        $this->sync($response['data']['attributes']);
        return $this;
    }

    public function updatePaymentMethodUrl(): string
    {
        $response = LaravelPayments::api()->getSubscription($this->provider_id);

        return $response['data']['attributes']['urls']['update_payment_method'];
    }

    /**
     * Swap the subscription to a new product plan.
     */
    public function swap(string $product, string $variant, array $attributes = []): self
    {
        $response = LaravelPayments::api()->swapSubscription($this->provider_id, [
            'data' => [
                'type' => 'subscriptions',
                'id' => $this->provider_id,
                'attributes' => array_merge([
                    'product_id' => $product,
                    'variant_id' => $variant,
                    'disable_prorations' => !$this->prorate,
                ], $attributes),
            ],
        ]);

        $this->sync($response['data']['attributes']);

        return $this;
    }

    public function swapAndInvoice(string $product, string $variant): self
    {
        return $this->swap($product, $variant, [
            'invoice_immediately' => true,
        ]);
    }

    /**
     * Sync the subscription with the given attributes.
     */
    public function sync(array $attributes): self
    {
        logger()->info('Syncing subscription', $attributes);
        $this->update([
            'status' => $attributes['status'],
            'product_id' => (string)$attributes['product_id'],
            'variant_id' => (string)$attributes['variant_id'],
            'card_brand' => $attributes['card_brand'] ?? null,
            'card_last_four' => $attributes['card_last_four'] ?? null,
            'pause_mode' => $attributes['pause']['mode'] ?? null,
            'pause_resumes_at' => isset($attributes['pause']['resumes_at']) ? Carbon::make($attributes['pause']['resumes_at']) : null,
            'trial_ends_at' => isset($attributes['trial_ends_at']) ? Carbon::make($attributes['trial_ends_at']) : null,
            'renews_at' => isset($attributes['renews_at']) ? Carbon::make($attributes['renews_at']) : null,
            'ends_at' => isset($attributes['ends_at']) ? Carbon::make($attributes['ends_at']) : null,
        ]);

        return $this;
    }

    public function active()
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * Determine if the subscription's trial has expired.
     */
    public function hasExpiredTrial(): bool
    {
        return $this->trial_ends_at && $this->trial_ends_at->isPast();
    }

    /**
     * Check if the subscription is on trial.
     */
    public function onTrial(): bool
    {
        return $this->status === self::STATUS_ON_TRIAL;
    }

    public function valid()
    {
        return $this->active() ||
            $this->onTrial() ||
            $this->pastDue() ||
            $this->canceled() ||
            ($this->paused() && $this->pause_mode === 'free');
    }

    /**
     * Check if the subscription is past due.
     */
    public function pastDue(): bool
    {
        return $this->status === self::STATUS_PAST_DUE;
    }

    /**
     * Check if the subscription is canceled.
     */
    public function canceled(): bool
    {
        return $this->status === self::STATUS_CANCELED;
    }

    /**
     * Check if the subscription is paused.
     */
    public function paused(): bool
    {
        return $this->status === self::STATUS_PAUSED;
    }

    /**
     * Check if the subscription is unpaid.
     */
    public function unpaid(): bool
    {
        return $this->status === self::STATUS_UNPAID;
    }

    /**
     * Check if the subscription is expired.
     */
    public function expired(): bool
    {
        return $this->status === self::STATUS_EXPIRED;
    }

    /**
     * Determine if the subscription is on a specific product.
     */
    public function hasProduct(string $productId): bool
    {
        return $this->product_id === $productId;
    }

    /**
     * Determine if the subscription is on a specific variant.
     */
    public function hasVariant(string $variantId): bool
    {
        return $this->variant_id === $variantId;
    }

    /**
     * Determine if the subscription has multiple prices.
     *
     * @return bool
     */
    public function hasMultiplePrices(): bool
    {
        return is_null($this->provider_price_id);
    }

    /**
     * Determine if the subscription has a specific price.
     *
     * @param string $price
     *
     * @return bool
     */
    public function hasPrice(string $price): bool
    {
        if ($this->hasMultiplePrices()) {
            return $this->items->contains(function (SubscriptionItem $item) use ($price) {
                return $item->provider_price_id === $price;
            });
        }
        return $this->provider_price_id === $price;
    }
}
