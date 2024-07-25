<?php

namespace Mosaiqo\LaravelPayments\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Mosaiqo\LaravelPayments\Models\Subscription;

class WebhookFailed
{
    use Dispatchable, SerializesModels;

    /**
     * The payload array.
     */
    public array $payload;

    public function __construct(array $payload)
    {
        $this->payload = $payload;
    }
}
