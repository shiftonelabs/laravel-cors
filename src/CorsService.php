<?php

namespace ShiftOneLabs\LaravelCors;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cors Service
 *
 * A lot of this code is derived from the asm89/stack-cors package. There were
 * a few things that needed tweaking, though, and the package's service class
 * uses private visibility, so I couldn't extend and override.
 *
 * @see https://github.com/asm89/stack-cors
 */
class CorsService
{
    /** @var array $options */
    protected $options;

    /**
     * Create a new service instance.
     *
     * @param  array  $options
     *
     * @return void
     */
    public function __construct(array $options = [])
    {
        $this->options = $this->normalizeOptions($options);
    }

    /**
     * Normalize the options into something more usable for the library.
     *
     * @param  array  $options
     *
     * @return array
     */
    protected function normalizeOptions(array $options = [])
    {
        $options += [
            'allowedOrigins' => [],
            'allowedOriginsPatterns' => [],
            'supportsCredentials' => false,
            'allowedHeaders' => [],
            'exposedHeaders' => [],
            'allowedMethods' => [],
            'maxAge' => 0,
        ];

        if (in_array('*', $options['allowedOrigins'])) {
            $options['allowedOrigins'] = true;
        }

        if (in_array('*', $options['allowedHeaders'])) {
            $options['allowedHeaders'] = true;
        } else {
            $options['allowedHeaders'] = array_map('strtolower', $options['allowedHeaders']);
        }

        if (in_array('*', $options['allowedMethods'])) {
            $options['allowedMethods'] = true;
        } else {
            $options['allowedMethods'] = array_map('strtoupper', $options['allowedMethods']);
        }

        return $options;
    }

    /**
     * Check if the request is a valid Cors request.
     *
     * @param  \Symfony\Component\HttpFoundation\Request  $request
     *
     * @return bool
     */
    public function isCorsRequest(Request $request)
    {
        return $request->headers->has('Origin') && $this->isCrossOrigin($request);
    }

    /**
     * Check if the request is a valid Cors preflight request.
     *
     * @param  \Symfony\Component\HttpFoundation\Request  $request
     *
     * @return bool
     */
    public function isPreflightRequest(Request $request)
    {
        return $this->isCorsRequest($request)
            && $request->getMethod() === 'OPTIONS'
            && $request->headers->has('Access-Control-Request-Method');
    }

    /**
     * Check the request origin to determine if the request is allowed.
     *
     * For the base CORS service, the request method is only checked during
     * preflight requests. If an invalid method is used for an actual
     * request, that should be handled at the application level.
     *
     * @param  \Symfony\Component\HttpFoundation\Request  $request
     *
     * @return bool
     */
    public function isActualRequestAllowed(Request $request)
    {
        return $this->isOriginAllowed($request);
    }

    /**
     * Add the valid Cors headers to the response.
     *
     * @param  \Symfony\Component\HttpFoundation\Response  $response
     * @param  \Symfony\Component\HttpFoundation\Request  $request
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function addActualRequestHeaders(Response $response, Request $request)
    {
        if (!$this->isActualRequestAllowed($request)) {
            return $response;
        }

        if (!$response->headers->has('Vary')) {
            $response->headers->set('Vary', 'Origin');
        } else {
            $response->headers->set('Vary', $response->headers->get('Vary') . ', Origin');
        }

        $response->headers->set('Access-Control-Allow-Origin', $request->headers->get('Origin'));

        if ($this->options['supportsCredentials']) {
            $response->headers->set('Access-Control-Allow-Credentials', 'true');
        }

        if ($this->options['exposedHeaders']) {
            $response->headers->set('Access-Control-Expose-Headers', implode(', ', $this->options['exposedHeaders']));
        }

        return $response;
    }

    /**
     * Get the full response for a Cors preflight request.
     *
     * This method deviates from the official preflight request algorithm. In the
     * official algorithm, if any of the origin, method, or headers is invalid,
     * no allow headers are added. So, if the origin is valid, but the header
     * is not supported, the allow origin header would not be added, and the
     * client would report an origin error, even though the origin is valid.
     *
     * This algorithm will only not add headers if the origin is not allowed (to
     * prevent leaking information). If the origin is allowed, then add all the
     * CORS response headers so the client can validate if the response is
     * valid and give the appropriate error message if not.
     *
     * The only exception to this algorithm is when the request method is not
     * allowed and it is a simple request method. For simple request methods,
     * clients do not validate the method against the allowed method header,
     * so we need to remove the allowed origin header to reject the request.
     *
     * @see https://www.w3.org/TR/cors/#resource-preflight-requests
     *
     * @param  \Symfony\Component\HttpFoundation\Request  $request
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handlePreflightRequest(Request $request)
    {
        // Preflight responses, even rejected ones, should return a 204
        // no body response.
        $response = $this->createResponse(null, 204);

        if (!$response->headers->has('Vary')) {
            $response->headers->set('Vary', 'Origin');
        } else {
            $response->headers->set('Vary', $response->headers->get('Vary') . ', Origin');
        }

        if (!$this->isOriginAllowed($request)) {
            return $response;
        }

        $response->headers->set('Access-Control-Allow-Origin', $request->headers->get('Origin'));

        // Clients ignore the Access-Control-Allow-Methods header for simple
        // request methods. In order to reject requests for simple methods
        // that aren't allowed, we disallow the origin.
        if (!$this->isMethodAllowed($request) && $this->isSimpleMethod($request)) {
            $response->headers->remove('Access-Control-Allow-Origin');
        }

        $allowMethods = $this->options['allowedMethods'] === true
            ? strtoupper($request->headers->get('Access-Control-Request-Method'))
            : implode(', ', $this->options['allowedMethods']);
        $response->headers->set('Access-Control-Allow-Methods', $allowMethods);

        $allowHeaders = $this->options['allowedHeaders'] === true
            ? strtoupper($request->headers->get('Access-Control-Request-Headers'))
            : implode(', ', $this->options['allowedHeaders']);
        $response->headers->set('Access-Control-Allow-Headers', $allowHeaders);

        if ($this->options['supportsCredentials']) {
            $response->headers->set('Access-Control-Allow-Credentials', 'true');
        }

        if ($this->options['maxAge']) {
            $response->headers->set('Access-Control-Max-Age', $this->options['maxAge']);
        }

        if ($this->isMethodAllowed($request) && $this->isHeadersAllowed($request)) {
            $response->headers->set('X-CORS-PREFLIGHT-SUCCESS', 'true');
        }

        return $response;
    }

    /**
     * Check if a given response is a successful preflight response.
     *
     * @param  \Symfony\Component\HttpFoundation\Response  $response
     *
     * @return bool
     */
    public function isPreflightSuccessful(Response $response)
    {
        // Only successful preflight responses will have this header.
        return $response->headers->has('X-CORS-PREFLIGHT-SUCCESS');
    }

    /**
     * Check if a given response is a rejected preflight response.
     *
     * @param  \Symfony\Component\HttpFoundation\Response  $response
     *
     * @return bool
     */
    public function isPreflightRejected(Response $response)
    {
        return !$this->isPreflightSuccessful($response);
    }

    /**
     * Check if the request is actually cross origin (the origin isn't the same
     * as the request host).
     *
     * @param \Symfony\Component\HttpFoundation\Request $request
     *
     * @return bool
     */
    protected function isCrossOrigin(Request $request)
    {
        return $request->headers->get('Origin') !== $request->getSchemeAndHttpHost();
    }

    /**
     * Check if the request origin is allowed by the CORS configuration.
     *
     * @param  \Symfony\Component\HttpFoundation\Request  $request
     *
     * @return bool
     */
    protected function isOriginAllowed(Request $request)
    {
        if ($this->options['allowedOrigins'] === true) {
            return true;
        }

        $origin = $request->headers->get('Origin');

        if (in_array($origin, $this->options['allowedOrigins'])) {
            return true;
        }

        foreach ($this->options['allowedOriginsPatterns'] as $pattern) {
            if (preg_match($pattern, $origin)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if the request method is allowed by the CORS configuration.
     *
     * @param  \Symfony\Component\HttpFoundation\Request  $request
     *
     * @return bool
     */
    protected function isMethodAllowed(Request $request)
    {
        if ($this->options['allowedMethods'] === true) {
            return true;
        }

        $method = $this->getActualRequestMethod($request);

        return in_array($method, $this->options['allowedMethods']);
    }

    /**
     * Check if the request headers are allowed by the CORS configuration.
     *
     * @param  \Symfony\Component\HttpFoundation\Request  $request
     *
     * @return bool
     */
    protected function isHeadersAllowed(Request $request)
    {
        if ($this->options['allowedHeaders'] === true || !$request->headers->has('Access-Control-Request-Headers')) {
            return true;
        }

        $headers = strtolower($request->headers->get('Access-Control-Request-Headers'));
        $headers = array_filter(array_map('trim', explode(',', $headers)));

        return empty(array_diff($headers, $this->options['allowedHeaders']));
    }

    /**
     * Check if this a simple request method.
     *
     * @see https://www.w3.org/TR/cors/#simple-method
     *
     * @param  \Symfony\Component\HttpFoundation\Request  $request
     *
     * @return bool
     */
    protected function isSimpleMethod(Request $request)
    {
        $method = $this->getActualRequestMethod($request);

        return in_array($method, ['GET', 'HEAD', 'POST']);
    }

    /**
     * Get the request method for the actual request, even if the current
     * request is a preflight request.
     *
     * @param  \Symfony\Component\HttpFoundation\Request  $request
     *
     * @return string
     */
    protected function getActualRequestMethod(Request $request)
    {
        return $this->isPreflightRequest($request)
            ? strtoupper($request->headers->get('Access-Control-Request-Method'))
            : $request->getMethod();
    }

    /**
     * Create a response object.
     *
     * @param  string|null  $content
     * @param  int  $status
     * @param  array  $headers
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function createResponse($content = '', $status = 200, $headers = [])
    {
        return new Response($content, $status, $headers);
    }
}
