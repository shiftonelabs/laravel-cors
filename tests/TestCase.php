<?php

namespace ShiftOneLabs\LaravelCors\Tests;

use Exception;
use Dotenv\Dotenv;
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

        // bootstrap: load environment
        $this->loadEnvironment();

        // bootstrap: register config
        $app->instance('config', $config = new Repository([]));

        // bootstrap: register providers
        $app->register(\ShiftOneLabs\LaravelCors\LaravelCorsServiceProvider::class);

        // bootstrap: boot providers
        $app->boot();

        return $app;
    }

    /**
     * Load the environment variables from the .env file.
     *
     * @return void
     */
    public function loadEnvironment()
    {
        try {
            if (method_exists(Dotenv::class, 'create')) {
                Dotenv::create(__DIR__.'/..')->safeLoad();
            } else {
                (new Dotenv(__DIR__.'/..'))->load();
            }
        } catch (Exception $e) {
            //
        }
    }
}
