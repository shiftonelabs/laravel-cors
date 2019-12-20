<?php

namespace ShiftOneLabs\LaravelCors;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

class CorsPolicy extends CorsService
{
    /**
     * Check the request origin and the request method to determine if the
     * request is allowed for this CORS policy.
     *
     * @param  \Symfony\Component\HttpFoundation\Request  $request
     *
     * @return bool
     */
    public function isActualRequestAllowed(SymfonyRequest $request)
    {
        return $this->isOriginAllowed($request) && $this->isMethodAllowed($request);
    }

    /**
     * Create the appropriate forbidden response based on the request.
     *
     * @param  \Illuminate\Http\Request  $request
     *
     * @return \Illuminate\Http\Response
     */
    public function createNotAllowedResponse(Request $request)
    {
        if (!$this->isOriginAllowed($request)) {
            return $this->createOriginNotAllowedResponse();
        }

        if (!$this->isMethodAllowed($request)) {
            return $this->createMethodNotAllowedResponse();
        }

        return $this->createResponse('Forbidden (cors).', 403);
    }

    /**
     * Create the response for an invalid request origin.
     *
     * @return \Illuminate\Http\Response
     */
    public function createOriginNotAllowedResponse()
    {
        return $this->createResponse('Origin not allowed.', 403);
    }

    /**
     * Create the response for an invalid request method.
     *
     * @return \Illuminate\Http\Response
     */
    public function createMethodNotAllowedResponse()
    {
        return $this->createResponse('Method not allowed.', 405);
    }

    /**
     * Create a response object.
     *
     * @param  string|null  $content
     * @param  int  $status
     * @param  array  $headers
     *
     * @return \Illuminate\Http\Response
     */
    protected function createResponse($content = '', $status = 200, $headers = [])
    {
        return new Response($content, $status, $headers);
    }
}
