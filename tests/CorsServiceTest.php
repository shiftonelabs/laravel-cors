<?php

namespace ShiftOneLabs\LaravelCors\Tests;

use PHPUnit_Framework_TestCase;
use ShiftOneLabs\LaravelCors\CorsService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class CorsServiceTest extends PHPUnit_Framework_TestCase
{
    use ReflectionTrait;

    public function test_it_disables_all_options_by_default()
    {
        $service = new CorsService();
        $options = $this->getRestrictedValue($service, 'options');

        $this->assertEquals([
            'allowedOrigins' => [],
            'allowedOriginsPatterns' => [],
            'supportsCredentials' => false,
            'allowedHeaders' => [],
            'exposedHeaders' => [],
            'allowedMethods' => [],
            'maxAge' => 0,
        ], $options);
    }

    public function test_it_determines_a_cors_request()
    {
        $service = new CorsService();

        $request = new Request();
        $request->headers->set('Host', 'foo.com');

        // Not cors when Origin header is missing.
        $this->assertFalse($service->isCorsRequest($request));

        // Not cors when same origin as host.
        $request->headers->set('Origin', 'http://foo.com');
        $this->assertFalse($service->isCorsRequest($request));

        // Is cors when cross origin.
        $request->headers->set('Origin', 'http://bar.com');
        $this->assertTrue($service->isCorsRequest($request));
    }

    public function test_it_determines_a_cors_preflight_request()
    {
        $service = new CorsService();

        $request = new Request();
        $request->headers->set('Host', 'foo.com');
        $request->headers->set('Origin', 'http://bar.com');
        $request->headers->set('Access-Control-Request-Method', 'GET');
        $request->setMethod('OPTIONS');

        // Is cors preflight when cross origin OPTIONS request is made with
        // the Access-Control-Request-Method header.
        $this->assertTrue($service->isPreflightRequest($request));

        // Not cors preflight when Origin header is missing.
        $request->headers->remove('Origin');
        $this->assertFalse($service->isPreflightRequest($request));

        // Not cors preflight when same origin as host.
        $request->headers->set('Origin', 'http://foo.com');
        $this->assertFalse($service->isPreflightRequest($request));

        // Not cors preflight when Access-Control-Request-Method header is missing.
        $request->headers->set('Origin', 'http://bar.com');
        $request->headers->remove('Access-Control-Request-Method', 'GET');
        $this->assertFalse($service->isPreflightRequest($request));

        // Not cors preflight when request method is not OPTIONS.
        $request->headers->set('Access-Control-Request-Method', 'GET');
        $request->setMethod('GET');
        $this->assertFalse($service->isPreflightRequest($request));
    }

    public function test_it_determines_if_a_request_is_allowed()
    {
        $request = new Request();
        $request->headers->set('Host', 'foo.com');
        $request->headers->set('Origin', 'http://bar.com');

        // Request is allowed for global origins.
        $service = new CorsService(['allowedOrigins' => ['*']]);
        $this->assertTrue($service->isActualRequestAllowed($request));

        // Request is allowed for specific origins.
        $service = new CorsService(['allowedOrigins' => ['http://bar.com', 'http://baz.com']]);
        $this->assertTrue($service->isActualRequestAllowed($request));

        // Request is not allowed for missing origins.
        $service = new CorsService(['allowedOrigins' => ['http://baz.com']]);
        $this->assertFalse($service->isActualRequestAllowed($request));

        // Request is not allowed for empty origins.
        $service = new CorsService(['allowedOrigins' => []]);
        $this->assertFalse($service->isActualRequestAllowed($request));
    }

    public function test_it_adds_headers_to_response()
    {
        $request = new Request();
        $request->headers->set('Host', 'foo.com');
        $request->headers->set('Origin', 'http://bar.com');

        // Response is not modified for non-allowed origins.
        $service = new CorsService(['allowedOrigins' => []]);
        $response = new Response();
        $service->addActualRequestHeaders($response, $request);
        $this->assertEquals((new Response())->headers->all(), $response->headers->all());

        // Headers added to response for allowed requests.
        $service = new CorsService(['allowedOrigins' => ['*']]);
        $response = new Response();
        $service->addActualRequestHeaders($response, $request);
        $this->assertEquals('Origin', $response->headers->get('Vary'));
        $this->assertEquals('http://bar.com', $response->headers->get('Access-Control-Allow-Origin'));
        $this->assertFalse($response->headers->has('Access-Control-Allow-Credentials'));
        $this->assertFalse($response->headers->has('Access-Control-Expose-Headers'));

        // Headers added to response for allowed requests with extra options.
        $service = new CorsService(['allowedOrigins' => ['*'], 'supportsCredentials' => true, 'exposedHeaders' => ['X-CUSTOM-1', 'X-CUSTOM-2']]);
        $response = new Response();
        $service->addActualRequestHeaders($response, $request);
        $this->assertEquals('Origin', $response->headers->get('Vary'));
        $this->assertEquals('http://bar.com', $response->headers->get('Access-Control-Allow-Origin'));
        $this->assertEquals('true', $response->headers->get('Access-Control-Allow-Credentials'));
        $this->assertEquals('X-CUSTOM-1, X-CUSTOM-2', $response->headers->get('Access-Control-Expose-Headers'));
    }

    public function test_it_handles_preflight_requests()
    {
        $unmodifiedResponse = new Response();

        $request = new Request();
        $request->headers->set('Host', 'foo.com');
        $request->headers->set('Origin', 'http://bar.com');
        $request->headers->set('Access-Control-Request-Method', 'GET');
        $request->headers->set('Access-Control-Request-Headers', 'X-CUSTOM-1, X-CUSTOM-2');
        $request->setMethod('OPTIONS');

        // Non-allowed origins
        // - successful response with only the Vary header added.
        // - preflight rejected
        $service = new CorsService();
        $response = $service->handlePreflightRequest($request);
        $this->assertEquals(204, $response->getStatusCode());
        $this->assertEmpty($response->getContent());
        $this->assertEquals(['vary'], array_values(array_diff($response->headers->keys(), $unmodifiedResponse->headers->keys())));
        $this->assertEquals('Origin', $response->headers->get('Vary'));
        $this->assertFalse($service->isPreflightSuccessful($response));
        $this->assertTrue($service->isPreflightRejected($response));

        // Allowed origin, not allowed simple method
        // - successful response with cors headers, minus origin
        // - preflight rejected
        $service = new CorsService(['allowedOrigins' => ['*']]);
        $response = $service->handlePreflightRequest($request);
        $this->assertEquals(204, $response->getStatusCode());
        $this->assertEmpty($response->getContent());
        $this->assertEquals(
            ['vary', 'access-control-allow-methods', 'access-control-allow-headers'],
            array_values(array_diff($response->headers->keys(), $unmodifiedResponse->headers->keys()))
        );
        $this->assertEquals('Origin', $response->headers->get('Vary'));
        $this->assertEquals('', $response->headers->get('Access-Control-Allow-Methods'));
        $this->assertEquals('', $response->headers->get('Access-Control-Allow-Headers'));
        $this->assertFalse($service->isPreflightSuccessful($response));
        $this->assertTrue($service->isPreflightRejected($response));

        // Allowed origin, not allowed non-simple method
        // - successful response with cors headers, including origin
        // - preflight rejected
        $request->headers->set('Access-Control-Request-Method', 'DELETE');
        $service = new CorsService(['allowedOrigins' => ['*'], 'allowedMethods' => ['GET', 'POST']]);
        $response = $service->handlePreflightRequest($request);
        $this->assertEquals(204, $response->getStatusCode());
        $this->assertEmpty($response->getContent());
        $this->assertEquals(
            ['vary', 'access-control-allow-origin', 'access-control-allow-methods', 'access-control-allow-headers'],
            array_values(array_diff($response->headers->keys(), $unmodifiedResponse->headers->keys()))
        );
        $this->assertEquals('Origin', $response->headers->get('Vary'));
        $this->assertEquals('http://bar.com', $response->headers->get('Access-Control-Allow-Origin'));
        $this->assertEquals('GET, POST', $response->headers->get('Access-Control-Allow-Methods'));
        $this->assertEquals('', $response->headers->get('Access-Control-Allow-Headers'));
        $this->assertFalse($service->isPreflightSuccessful($response));
        $this->assertTrue($service->isPreflightRejected($response));

        // Allowed origin, allowed method, not allowed header
        // - successful response with cors headers
        // - preflight rejected
        $service = new CorsService(['allowedOrigins' => ['*'], 'allowedMethods' => ['*']]);
        $response = $service->handlePreflightRequest($request);
        $this->assertEquals(204, $response->getStatusCode());
        $this->assertEmpty($response->getContent());
        $this->assertEquals(
            ['vary', 'access-control-allow-origin', 'access-control-allow-methods', 'access-control-allow-headers'],
            array_values(array_diff($response->headers->keys(), $unmodifiedResponse->headers->keys()))
        );
        $this->assertEquals('Origin', $response->headers->get('Vary'));
        $this->assertEquals('http://bar.com', $response->headers->get('Access-Control-Allow-Origin'));
        $this->assertEquals('DELETE', $response->headers->get('Access-Control-Allow-Methods'));
        $this->assertEquals('', $response->headers->get('Access-Control-Allow-Headers'));
        $this->assertFalse($service->isPreflightSuccessful($response));
        $this->assertTrue($service->isPreflightRejected($response));

        // Allowed origin, allowed method, allowed header, all preflight options
        // - successful response with all cors headers
        // - preflight successful
        $service = new CorsService([
            'allowedOrigins' => ['*'],
            'supportsCredentials' => true,
            'allowedHeaders' => ['*'],
            'allowedMethods' => ['*'],
            'maxAge' => 3600,
        ]);
        $response = $service->handlePreflightRequest($request);
        $this->assertEquals(204, $response->getStatusCode());
        $this->assertEmpty($response->getContent());
        $this->assertEquals(
            ['vary', 'access-control-allow-origin', 'access-control-allow-methods', 'access-control-allow-headers', 'access-control-allow-credentials', 'access-control-max-age', 'x-cors-preflight-success'],
            array_values(array_diff($response->headers->keys(), $unmodifiedResponse->headers->keys()))
        );
        $this->assertEquals('Origin', $response->headers->get('Vary'));
        $this->assertEquals('http://bar.com', $response->headers->get('Access-Control-Allow-Origin'));
        $this->assertEquals('DELETE', $response->headers->get('Access-Control-Allow-Methods'));
        $this->assertEquals('X-CUSTOM-1, X-CUSTOM-2', $response->headers->get('Access-Control-Allow-Headers'));
        $this->assertEquals('true', $response->headers->get('Access-Control-Allow-Credentials'));
        $this->assertEquals('3600', $response->headers->get('Access-Control-Max-Age'));
        $this->assertEquals('true', $response->headers->get('X-CORS-PREFLIGHT-SUCCESS'));
        $this->assertTrue($service->isPreflightSuccessful($response));
        $this->assertFalse($service->isPreflightRejected($response));
    }
}
