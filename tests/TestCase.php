<?php

namespace ShiftOneLabs\LaravelCors\Tests;

use Illuminate\Config\Repository;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

/**
 * @property \Illuminate\Foundation\Application $app
 */
class TestCase extends BaseTestCase
{
    use ReflectionTrait;

    public function createApplication()
    {
        $app = new Application();
        $app->singleton(\Illuminate\Contracts\Http\Kernel::class, \Illuminate\Foundation\Http\Kernel::class);
        $app->singleton(\Illuminate\Contracts\Console\Kernel::class, \Illuminate\Foundation\Console\Kernel::class);

        // bootstrap: register config
        $app->instance('config', $config = new Repository([]));

        // bootstrap: register providers
        $app->register(\ShiftOneLabs\LaravelCors\LaravelCorsServiceProvider::class);

        // bootstrap: boot providers
        $app->boot();

        return $app;
    }
}
