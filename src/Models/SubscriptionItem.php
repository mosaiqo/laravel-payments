<?php

namespace Mosaiqo\LaravelPayments\Models;

use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Mosaiqo\LaravelPayments\Database\Factories\SubscriptionFactory;
use Mosaiqo\LaravelPayments\Database\Factories\SubscriptionItemFactory;
use Mosaiqo\LaravelPayments\LaravelPayments;
use Mosaiqo\LaravelPayments\Models\Concerns\Prorates;

/**
 * @property int     $id
 * @property string  $provider
 * @property string  $provider_id
 * @property string  $provider_subscription_id
 * @property string  $provider_product_id
 * @property string  $provider_price_id
 * @property boolean $is_usage_based
 * @property ?int    $quantity
 */
class SubscriptionItem extends Model
{
    use HasFactory;

    protected static string $factory = SubscriptionItemFactory::class;

    protected $table = 'payments_subscription_items';

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
    protected $casts = [];

    /**
     * Get the subscription that owns the item.
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }
}
