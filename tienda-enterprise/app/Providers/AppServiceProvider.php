<?php

namespace App\Providers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Detector de N+1 en desarrollo. En vez de lanzar una excepción solo lo
        // registra en storage/logs/laravel.log, para poder mostrar la demo /demo/n-mas-1.
        Model::preventLazyLoading(! $this->app->isProduction());

        Model::handleLazyLoadingViolationUsing(function (Model $model, string $relation): void {
            Log::warning('N+1 detectado: lazy loading de '.$model::class."::{$relation}");
        });
    }
}
