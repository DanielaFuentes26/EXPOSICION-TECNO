<?php

namespace App\Providers;

use App\Repositories\Contracts\OrderRepositoryInterface;
use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Repositories\Eloquent\EloquentOrderRepository;
use App\Repositories\Eloquent\EloquentProductRepository;
use Illuminate\Support\ServiceProvider;

/**
 * Aquí se decide qué implementación recibe quien pida cada interfaz.
 */
class RepositoryServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, class-string>
     */
    public array $bindings = [
        ProductRepositoryInterface::class => EloquentProductRepository::class,
        OrderRepositoryInterface::class => EloquentOrderRepository::class,
    ];
}
