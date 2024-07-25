<?php

namespace Mosaiqo\LaravelPayments\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Mosaiqo\LaravelPayments\Database\Factories\CustomerFactory;
use Mosaiqo\LaravelPayments\Models\Concerns\BootsProviderScope;

class Customer extends Model
{
    use BootsProviderScope;
    use HasFactory;

    protected $table = 'payments_customers';

    protected static string $factory = CustomerFactory::class;

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
        'trial_ends_at' => 'datetime',
    ];


    /**
     * Get the billable model related to the customer.
     */
    public function billable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): CustomerFactory
    {
        return CustomerFactory::new();
    }

    /**
     * Determine if the customer is on a "generic" trial at the model level.
     */
    public function onGenericTrial(): bool
    {
        return $this->trial_ends_at && $this->trial_ends_at->isFuture();
    }

    /**
     * Determine if the customer has an expired "generic" trial at the model level.
     */
    public function hasExpiredGenericTrial(): bool
    {
        return $this->trial_ends_at && $this->trial_ends_at->isPast();
    }

}
