<?php

namespace ShiftOneLabs\LaravelCors\Tests;

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Support\ServiceProvider;
use ShiftOneLabs\LaravelCors\CorsPolicyManager;
use ShiftOneLabs\LaravelCors\LaravelCorsServiceProvider;
use ShiftOneLabs\LaravelCors\Http\Middleware\CorsWrapper;

class ServiceProviderTest extends TestCase
{
    public function test_laravel_cors_service_provider_is_loaded()
    {
        $providers = $this->app->getLoadedProviders();

        $this->assertArrayHasKey(LaravelCorsServiceProvider::class, $providers);
        $this->assertTrue($providers[LaravelCorsServiceProvider::class]);
    }

    public function test_service_provider_binds_cors_policy_manager()
    {
        $this->assertTrue($this->app->bound(CorsPolicyManager::class));
        $this->assertInstanceOf(CorsPolicyManager::class, $this->app->make(CorsPolicyManager::class));
    }

    public function test_service_provider_publishes_config()
    {
        $this->assertNotEmpty(ServiceProvider::pathsToPublish(LaravelCorsServiceProvider::class, 'config'));
    }

    public function test_service_provider_prepends_middleware()
    {
        $kernel = $this->app->make(Kernel::class);

        $this->assertTrue($kernel->hasMiddleware(CorsWrapper::class));
    }
}
