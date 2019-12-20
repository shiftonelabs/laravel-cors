<?php

namespace ShiftOneLabs\LaravelCors\Http\Middleware;

use Closure;
use Illuminate\Routing\Router;
use Symfony\Component\HttpFoundation\Response;
use ShiftOneLabs\LaravelCors\CorsPolicyManager;

class ApplyCorsPolicy
{
    /** @var \ShiftOneLabs\LaravelCors\CorsPolicyManager $corsPolicyManager */
    protected $corsPolicyManager;

    /**
     * Create a new middleware instance.
     *
     * @param  \ShiftOneLabs\LaravelCors\CorsPolicyManager  $corsPolicyManager
     *
     * @return void
     */
    public function __construct(CorsPolicyManager $corsPolicyManager)
    {
        $this->corsPolicyManager = $corsPolicyManager;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string|null  $profile
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle($request, Closure $next, $profile = null)
    {
        $cors = $this->corsPolicyManager->make($profile);

        if (!$cors->isCorsRequest($request)) {
            return $next($request);
        }

        if ($cors->isPreflightRequest($request)) {
            $preflightResponse = $cors->handlePreflightRequest($request);

            // Stop the call stack once a rejected preflight response is made.
            if ($cors->isPreflightRejected($preflightResponse)) {
                return $this->prepareResponse($preflightResponse);
            }

            // If this preflight was successful, move on to the next.
            $response = $next($request);

            // The destination core is plain text, not a response object. If we
            // hit the core, or the last middleware failed, return the current
            // preflight response. If we're on the way down the stack,
            // continue to return the last preflight response.
            return $this->prepareResponse($response instanceof Response && $response->isSuccessful() ? $response : $preflightResponse);
        }

        // Stop the call stack once a CORS request is rejected.
        if (!$cors->isActualRequestAllowed($request)) {
            return $this->prepareResponse($cors->createNotAllowedResponse($request));
        }

        $response = Router::toResponse($request, $next($request));

        // CORS policy already applied.
        if ($response->headers->has('X-S1L-CORS-HANDLED')) {
            return $this->prepareResponse($response);
        }

        return $this->prepareResponse($cors->addActualRequestHeaders($response, $request));
    }

    /**
     * Prepare the response that has been handled by the CORS middleware.
     *
     * @param  \Symfony\Component\HttpFoundation\Response  $response
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function prepareResponse(Response $response)
    {
        $response->headers->set('X-S1L-CORS-HANDLED', true);

        return $response;
    }
}
