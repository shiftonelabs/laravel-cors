<?php

namespace ShiftOneLabs\LaravelCors\Tests;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;

class CorsWrapperTest extends TestCase
{
    public function test_it_runs_request_through_middleware()
    {
        Route::get('integration-test', ['middleware' => ['cors.policy'], function () {
            return new Response('valid');
        }]);

        $response = $this->get('integration-test');

        // Laravel 5.4 changed the response from $this to a TestResponse object.
        if ($response === $this) {
            $response = $this->response;
        }

        $this->assertEquals(200, $response->getStatusCode());
    }
}
