<?php

namespace ShiftOneLabs\LaravelCors\Tests;

use LogicException;
use ShiftOneLabs\LaravelCors\CorsPolicy;
use ShiftOneLabs\LaravelCors\CorsPolicyManager;

class CorsPolicyManagerTest extends TestCase
{
    public function test_policy_manager_makes_default_policy()
    {
        $manager = $this->app->make(CorsPolicyManager::class);

        $this->assertInstanceOf(CorsPolicy::class, $manager->make());
    }

    public function test_policy_manager_makes_named_policies()
    {
        $manager = $this->app->make(CorsPolicyManager::class);

        $this->assertInstanceOf(CorsPolicy::class, $manager->make('open'));
    }

    public function test_policy_manager_throws_exception_for_missing_policies()
    {
        $profile = 'missing';
        $manager = $this->app->make(CorsPolicyManager::class);

        $this->setExpectedException(LogicException::class, 'CORS profile ['.$profile.'] not found.');

        $manager->make($profile);
    }
}
