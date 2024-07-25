<?php

namespace Mosaiqo\LaravelPayments\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Mosaiqo\LaravelPayments\Models\Subscription;

class LicenseKeyUpdated
{
    use Dispatchable, SerializesModels;

    /**
     * The billable entity.
     */
    public Model $billable;

    /**
     * The payload array.
     */
    public array $payload;

    public function __construct(Model $billable, array $payload)
    {
        $this->billable = $billable;
        $this->payload = $payload;
    }
}
