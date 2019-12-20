<?php

namespace ShiftOneLabs\LaravelCors\Tests\Fakes;

use Exception;
use Illuminate\Foundation\Exceptions\Handler;
use Symfony\Component\HttpKernel\Exception\HttpException;

class DebugExceptionHandler extends Handler
{
    /**
     * Report or log an exception.
     *
     * @param  \Exception  $e
     * @return void
     */
    public function report(Exception $e)
    {
        //
    }

    /**
     * Render the given HttpException.
     *
     * @param  \Symfony\Component\HttpKernel\Exception\HttpException  $e
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function renderHttpException(HttpException $e)
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
