<?php

namespace Mosaiqo\LaravelPayments\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WebhookSkipped
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
