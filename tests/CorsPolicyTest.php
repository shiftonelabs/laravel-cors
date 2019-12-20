<?php

namespace ShiftOneLabs\LaravelCors\Tests;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use PHPUnit\Framework\TestCase;
use ShiftOneLabs\LaravelCors\CorsPolicy;

class CorsPolicyTest extends TestCase
{
    public function test_it_determines_if_a_request_is_allowed()
    {
        $request = new Request();
        $request->headers->set('Host', 'foo.com');
        $request->headers->set('Origin', 'http://bar.com');
        $request->setMethod('GET');

        // Request is allowed for global origins and global methods.
        $policy = new CorsPolicy(['allowedOrigins' => ['*'], 'allowedMethods' => ['*']]);
        $this->assertTrue($policy->isActualRequestAllowed($request));

        // Request is allowed for specific origins and specific methods.
        $policy = new CorsPolicy(['allowedOrigins' => ['http://bar.com', 'http://baz.com'], 'allowedMethods' => ['GET', 'POST']]);
        $this->assertTrue($policy->isActualRequestAllowed($request));

        // Request is not allowed for missing methods.
        $policy = new CorsPolicy(['allowedOrigins' => ['*'], 'allowedMethods' => ['POST']]);
        $this->assertFalse($policy->isActualRequestAllowed($request));

        // Request is not allowed for empty methods.
        $policy = new CorsPolicy(['allowedOrigins' => ['*'], 'allowedMethods' => []]);
        $this->assertFalse($policy->isActualRequestAllowed($request));

        // Request is not allowed for missing origins.
        $policy = new CorsPolicy(['allowedOrigins' => ['http://baz.com'], 'allowedMethods' => ['*']]);
        $this->assertFalse($policy->isActualRequestAllowed($request));

        // Request is not allowed for empty origins.
        $policy = new CorsPolicy(['allowedOrigins' => [], 'allowedMethods' => ['*']]);
        $this->assertFalse($policy->isActualRequestAllowed($request));
    }

    public function test_it_creates_origin_not_allowed_response()
    {
        $policy = new CorsPolicy();
        $response = $policy->createOriginNotAllowedResponse();

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function test_it_creates_method_not_allowed_response()
    {
        $policy = new CorsPolicy();
        $response = $policy->createMethodNotAllowedResponse();

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(405, $response->getStatusCode());
    }

    public function test_it_creates_not_allowed_response()
    {
        $request = new Request();
        $request->headers->set('Host', 'foo.com');
        $request->headers->set('Origin', 'http://bar.com');
        $request->setMethod('GET');

        // Create 403 for origin not allowed.
        $policy = new CorsPolicy(['allowedOrigins' => [], 'allowedMethods' => []]);
        $response = $policy->createNotAllowedResponse($request);
        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(403, $response->getStatusCode());

        // Create 405 for method not allowed.
        $policy = new CorsPolicy(['allowedOrigins' => ['*'], 'allowedMethods' => []]);
        $response = $policy->createNotAllowedResponse($request);
        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(405, $response->getStatusCode());

        // Create 403 for any other reason.
        $policy = new CorsPolicy(['allowedOrigins' => ['*'], 'allowedMethods' => ['*']]);
        $response = $policy->createNotAllowedResponse($request);
        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(403, $response->getStatusCode());
    }
}
