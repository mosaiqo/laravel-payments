<?php

namespace Mosaiqo\LaravelPayments;

class IntervalKeyMapper
{
    public static function map($interval, $count = 1)
    {
        $key = "{$interval}";

        if ($count > 1) {
            $key = "every-{$count}-{$interval}";
        }

        return $key;
    }
}
