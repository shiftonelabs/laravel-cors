<?php

namespace ShiftOneLabs\LaravelCors;

use LogicException;
use Illuminate\Support\Arr;
use Illuminate\Support\Manager;

class CorsPolicyManager extends Manager
{
    /** @var array $config */
    protected $config;

    /**
     * Create a new manager instance.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @param  array  $config
     *
     * @return void
     */
    public function __construct($app, $config)
    {
        parent::__construct($app);

        $this->config = $config;
    }

    /**
     * Get the default driver name.
     *
     * @return string
     */
    public function getDefaultDriver()
    {
        return $this->config('default');
    }

    /**
     * Create a new Cors policy instance.
     *
     * @param  string  $profile
     *
     * @return \ShiftOneLabs\LaravelCors\CorsPolicy
     */
    public function make($profile = null)
    {
        return $this->driver($profile);
    }

    /**
     * Create a new driver instance.
     *
     * @param  string  $profile
     *
     * @return \ShiftOneLabs\LaravelCors\CorsPolicy
     *
     * @throws \LogicException
     */
    protected function createDriver($profile)
    {
        if (isset($this->customCreators[$profile])) {
            return $this->callCustomCreator($profile);
        }

        $config = $this->config('profiles.'.$profile);

        if (empty($config)) {
            throw new LogicException('CORS profile ['.$profile.'] not found.');
        }

        return new CorsPolicy($config);
    }

    /**
     * Get a value from the config array.
     *
     * @param  string  $key
     * @param  mixed|null  $default
     *
     * @return mixed
     */
    protected function config($key = null, $default = null)
    {
        if (empty($key)) {
            return $this->config;
        }

        return Arr::get($this->config, $key, $default);
    }
}
