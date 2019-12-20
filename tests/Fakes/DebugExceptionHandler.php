<?php

namespace ShiftOneLabs\LaravelCors\Tests\Fakes;

use Exception;
use Illuminate\Foundation\Exceptions\Handler;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class DebugExceptionHandler extends Handler
{
    /**
     * Render the given HttpException.
     *
     * @param  \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface  $e
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function renderHttpException(HttpExceptionInterface $e)
    {
        return $this->convertExceptionToResponse($e);
    }

    /**
     * Get the response content for the given exception.
     *
     * @param  \Exception  $e
     * @return string
     */
    protected function renderExceptionContent(Exception $e)
    {
        return $this->renderExceptionWithSymfony($e, true);
    }
}
