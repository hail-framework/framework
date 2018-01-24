<?php

namespace Hail\Http\Middleware;

use Hail\Debugger\Debugger;
use Hail\Http\Exception\HttpErrorException;
use Psr\Http\{
    Server\MiddlewareInterface,
    Server\RequestHandlerInterface,
    Message\ServerRequestInterface,
    Message\ResponseInterface
};

class ErrorHandler implements MiddlewareInterface
{
    /**
     * @var callable|null The status code validator
     */
    private $statusCodeValidator;

    /**
     * Configure the status code validator.
     *
     * @param callable $statusCodeValidator
     *
     * @return self
     */
    public function statusCode(callable $statusCodeValidator)
    {
        $this->statusCodeValidator = $statusCodeValidator;

        return $this;
    }

    /**
     * Process a server request and return a response.
     *
     * @param ServerRequestInterface $request
     * @param RequestHandlerInterface      $handler
     *
     * @return ResponseInterface
     * @throws \Throwable
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = Debugger::start($request);
        if ($response !== null) {
            return $response;
        }

        \ob_start();

        try {
            $response = $handler->handle($request);

            if ($this->isError($response->getStatusCode())) {
                throw new HttpErrorException(
                    $response->getReasonPhrase(),
                    $response->getStatusCode()
                );
            }

            return Debugger::writeToResponse($response);
        } catch (\Throwable $exception) {
            return Debugger::exceptionToResponse($exception);
        }
    }

    /**
     * Check whether the status code represents an error or not.
     *
     * @param int $statusCode
     *
     * @return bool
     */
    private function isError($statusCode)
    {
        if ($this->statusCodeValidator) {
            return ($this->statusCodeValidator)($statusCode);
        }

        return $statusCode >= 400 && $statusCode < 600;
    }
}