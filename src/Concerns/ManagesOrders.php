<?php

namespace Mosaiqo\LaravelPayments\Concerns;

use Mosaiqo\LaravelPayments\LaravelPayments;

trait ManagesOrders
{

    public function orders(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        $model = LaravelPayments::resolveOrderModel();
        return $this->morphMany($model, 'billable')->orderByDesc('created_at');
    }

}
