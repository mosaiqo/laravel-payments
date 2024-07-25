<?php

namespace Mosaiqo\LaravelPayments;

use Mosaiqo\LaravelPayments\Concerns\ManagesCheckouts;
use Mosaiqo\LaravelPayments\Concerns\ManagesCustomer;
use Mosaiqo\LaravelPayments\Concerns\ManagesOrders;
use Mosaiqo\LaravelPayments\Concerns\ManagesSubscriptions;
use Mosaiqo\LaravelPayments\Concerns\PerformsCharges;
use Mosaiqo\LaravelPayments\Concerns\Prorates;

trait Billable
{
    use PerformsCharges;
    use ManagesCheckouts;
    use ManagesSubscriptions;
    use ManagesCustomer;
    use ManagesOrders;
}
