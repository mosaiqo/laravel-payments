<?php

namespace Mosaiqo\LaravelPayments\Models;


use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Mosaiqo\LaravelPayments\Database\Factories\OrderFactory;
use Mosaiqo\LaravelPayments\LaravelPayments;

/**
 * @property mixed $status
 * @property \Mosaiqo\LaravelPayments\Models\Customer $customer
 */
class Order extends Model
{
    use HasFactory;

    const STATUS_PENDING = 'pending';

    const STATUS_FAILED = 'failed';

    const STATUS_PAID = 'paid';

    const STATUS_REFUNDED = 'refunded';

    protected $table = 'payments_orders';

    protected static string $factory = OrderFactory::class;

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
        'subtotal' => 'integer',
        'discount_total' => 'integer',
        'tax' => 'integer',
        'total' => 'integer',
        'refunded' => 'boolean',
        'refunded_at' => 'datetime',
        'ordered_at' => 'datetime',
    ];

    /**
     * Get the billable model related to the customer.
     */
    public function billable(): MorphTo
    {
        return $this->customer->billable();
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'payments_customer_id', 'id');
    }

    /**
     * Filter query by pending.
     */
    public function scopePending(Builder $query): void
    {
        $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Filter query by failed.
     */
    public function scopeFailed(Builder $query): void
    {
        $query->where('status', self::STATUS_FAILED);
    }

    /**
     * Filter query by paid.
     */
    public function scopePaid(Builder $query): void
    {
        $query->where('status', self::STATUS_PAID);
    }

    /**
     * Filter query by refunded.
     */
    public function scopeRefunded(Builder $query): void
    {
        $query->where('status', self::STATUS_REFUNDED);
    }



    /**
     * Check if the order is pending.
     */
    public function pending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Check if the order is paid.
     */
    public function paid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }

    /**
     * Check if the order is failed.
     */
    public function failed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }


    /**
     * Check if the order is refunded.
     */
    public function refunded(): bool
    {
        return $this->status === self::STATUS_REFUNDED;
    }

    /**
     * Determine if the order is for a specific product.
     */
    public function hasProduct(string $productId): bool
    {
        return $this->product_id === $productId;
    }

    /**
     * Determine if the order is for a specific variant.
     */
    public function hasVariant(string $variantId): bool
    {
        return $this->variant_id === $variantId;
    }

    /**
     * Sync the order with the given attributes.
     */
    public function sync(array $attributes): self
    {
        $this->update([
            'customer_id' => $attributes['customer_id'],
            'product_id' => (string) $attributes['first_order_item']['product_id'],
            'variant_id' => (string) $attributes['first_order_item']['variant_id'],
            'identifier' => $attributes['identifier'],
            'order_number' => $attributes['order_number'],
            'currency' => $attributes['currency'],
            'subtotal' => $attributes['subtotal'],
            'discount_total' => $attributes['discount_total'],
            'tax' => $attributes['tax'],
            'total' => $attributes['total'],
            'tax_name' => $attributes['tax_name'],
            'status' => $attributes['status'],
            'receipt_url' => $attributes['urls']['receipt'] ?? null,
            'refunded' => $attributes['refunded'],
            'refunded_at' => isset($attributes['refunded_at']) ? Carbon::make($attributes['refunded_at']) : null,
            'ordered_at' => isset($attributes['created_at']) ? Carbon::make($attributes['created_at']) : null,
        ]);

        return $this;
    }
}
