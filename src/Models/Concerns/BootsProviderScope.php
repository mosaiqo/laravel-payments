<?php


namespace Mosaiqo\LaravelPayments\Models\Concerns;

trait BootsProviderScope {

    static public bool $withoutProviderScope = false;


    /**
     * @return void
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    protected static function bootBootsProviderScope(): void
    {
        if (!static::$withoutProviderScope) {

            static::creating(function ($model) {
                $model->provider = config('payments.provider');
            });
        }
    }
}
