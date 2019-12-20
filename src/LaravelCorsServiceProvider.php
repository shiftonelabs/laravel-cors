<?php

namespace ShiftOneLabs\LaravelCors;

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Support\ServiceProvider;
use ShiftOneLabs\LaravelCors\Http\Middleware\CorsWrapper;

class LaravelCorsServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @param  \Illuminate\Foundation\Http\Kernel  $kernel
     *
     * @return void
     */
    public function boot(Kernel $kernel)
    {
        $this->publishes([__DIR__.'/../config/cors.php' => config_path('cors.php')], 'config');

        $kernel->prependMiddleware(CorsWrapper::class);
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/../config/cors.php', 'cors');

        $this->app->singleton(CorsPolicyManager::class, function ($app) {
            return new CorsPolicyManager($app, $app['config']->get('cors'));
        });
    }
}
