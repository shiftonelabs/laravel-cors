<?php

namespace ShiftOneLabs\LaravelCors\Http\Middleware;

use Closure;
use Throwable;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Illuminate\Routing\Pipeline;
use Illuminate\Container\Container;
use Illuminate\Contracts\Http\Kernel;
use ShiftOneLabs\LaravelCors\CorsService;
use Symfony\Component\HttpFoundation\Response;

class CorsWrapper
{
    /** @var \Illuminate\Container\Container $container */
    protected $container;

    /** @var \Illuminate\Foundation\Http\Kernel $kernel */
    protected $kernel;

    /** @var \Illuminate\Routing\Router $router */
    protected $router;

    /** @var \ShiftOneLabs\LaravelCors\CorsService $cors */
    protected $cors;

    /**
     * Create a new middleware instance.
     *
     * @param  \Illuminate\Foundation\Http\Kernel  $kernel
     * @param  \Illuminate\Routing\Router  $router
     *
     * @return void
     */
    public function __construct(Container $container, Kernel $kernel, Router $router, CorsService $cors)
    {
        $this->container = $container;
        $this->kernel = $kernel;
        $this->router = $router;
        $this->cors = $cors;
    }

    /**
     * Handle an incoming request.
     *
     * This middleware is intended to be a global middleware that wraps around
     * the entire stack. The "before" part will be the first middleware to
     * take action, and the "after" part will be the last.
     *
     * The "before" middleware section is responsible for making sure this
     * middleware only handles CORS requests, as well as short-circuiting
     * the call stack for CORS preflight requests.
     *
     * This "after" middleware section is responsible for acting as a fallback
     * to handle CORS requests that run into errors. For example, if an error
     * occurs before a CORS policy can be applied, the returned error won't
     * include the proper CORS headers, and the client won't be able to
     * access the error message. This middleware would handle that and
     * apply the proper headers.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     *
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        // Skip if it is not a CORS request.
        if (!$this->cors->isCorsRequest($request)) {
            return $next($request);
        }

        // Handle CORS preflight requests and short circuit the call stack.
        if ($this->cors->isPreflightRequest($request)) {
            return $this->handlePreflightRequest($request, $next);
        }

        // Run the call stack.
        $response = $next($request);

        // Ensure we are working with a Response object.
        $response = Router::toResponse($request, $response);

        // Skip if the CORS middleware has already processed the request. If
        // the request is forbidden or has a bad method/header, the normal
        // CORS headers aren't added, so we check for a custom one.
        if ($response->headers->has('X-S1L-CORS-HANDLED')) {
            return $this->prepareResponse($response);
        }

        // If the response is not an error, there were no exceptions to
        // get in the way of all the middleware getting processed.
        if ($response->getStatusCode() < 400 && empty($response->exception)) {
            return $response;
        }

        $route = $request->route();

        // The route won't be set if it wasn't found or if there was an error in
        // a global before middleware. See if we can find the route to get the
        // route specific middleware. If not, global will still apply.
        if (empty($route)) {
            try {
                $route = $this->router->getRoutes()->match($request);
            } catch (Throwable $e) {
                $route = null;
            }
        }

        $corsMiddleware = $this->gatherCorsMiddleware($route);

        // Skip if there are no CORS middleware to run the request through.
        if (empty($corsMiddleware)) {
            return $response;
        }

        // Process all the CORS middleware for the request.
        return $this->prepareResponse(
            (new Pipeline($this->container))
                ->send($request)
                ->through($corsMiddleware)
                ->then(function ($request) use ($response) {
                    return $response;
                })
        );
    }

    /**
     * Handle a CORS preflight request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function handlePreflightRequest($request, Closure $next)
    {
        $corsMiddleware = $this->gatherCorsPreflightMiddleware($request);

        // Skip if there are no CORS middleware to run the request through.
        if (empty($corsMiddleware)) {
            return $next($request);
        }

        // Process all the CORS middleware for the request.
        return $this->prepareResponse(
            (new Pipeline($this->container))
                ->send($request)
                ->through($corsMiddleware)
                ->then(function ($request) {
                    return 'CORS preflight onion core.';
                })
        );
    }

    /**
     * Get all the CORS middleware applied to a route, including the global
     * middleware.
     *
     * @param  \Illuminate\Routing\Route  $route
     *
     * @return array
     */
    protected function gatherCorsMiddleware(Route $route = null)
    {
        $corsMiddleware = [];

        // Get the global CORS policy middleware.
        if ($this->kernel->hasMiddleware(ApplyCorsPolicy::class)) {
            $corsMiddleware[] = ApplyCorsPolicy::class;
        }

        if (empty($route)) {
            return $corsMiddleware;
        }

        // Gather all the route CORS policy middlewares.
        foreach ($this->router->gatherRouteMiddleware($route) as $routeMiddleware) {
            list($class, $parameters) = array_pad(explode(':', $routeMiddleware, 2), 2, '');
            if ($class == ApplyCorsPolicy::class) {
                $corsMiddleware[] = $routeMiddleware;
            }
        }

        return $corsMiddleware;
    }

    /**
     * Get all the CORS middleware applied to a route specified by a preflight
     * request.
     *
     * @param  \Illuminate\Http\Request  $request
     *
     * @return array
     */
    protected function gatherCorsPreflightMiddleware($request)
    {
        // The preflight request uses the OPTIONS method. We need to temporarily
        // replace this method with the preflight target method in order for
        // the router to be able to find the intended actual route.

        // Save off the original method.
        $originalMethod = $request->getMethod();

        // Update the request with the intended method.
        $method = $request->headers->get('Access-Control-Request-Method');
        $request->setMethod($method);

        // Find the route with the proper request.
        try {
            $route = $this->router->getRoutes()->match($request);
        } catch (Throwable $e) {
            $route = null;
        }

        // Reset the method on the request with the original method.
        $request->setMethod($originalMethod);

        return $this->gatherCorsMiddleware($route);
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
        $response->headers->remove('X-S1L-CORS-HANDLED');

        return $response;
    }
}
