<?php

namespace Mosaiqo\LaravelPayments\Models;

use Illuminate\Database\Eloquent\Model;

class Webhooks extends Model
{
    protected $table = 'payments_webhooks';

    protected $guarded = [];

    protected $casts = [
        'body' => 'json',
        'headers' => 'json',
        'processed_at' => 'datetime',
    ];
}

