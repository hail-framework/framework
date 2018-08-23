<?php

namespace Hail\Http\Client\Plugin;

use Hail\Http\Client\RequestHandlerInterface;
use Hail\Promise\PromiseInterface;
use Hail\Http\Client\Psr\ClientException;
use Hail\Http\Client\Psr\Exception\HttpException;
use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;

/**
 * Log request, response and exception for an HTTP Client.
 *
 * @author Joel Wurtz <joel.wurtz@gmail.com>
 */
final class LoggerPlugin implements PluginInterface
{
    private $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public static function formatRequest(RequestInterface $request, $full = false)
    {
        $message = \sprintf(
            '%s %s HTTP/%s',
            $request->getMethod(),
            $request->getUri()->__toString(),
            $request->getProtocolVersion()
        );

        if ($full) {
            $message = self::appendMessage($request, $message);
        }

        return $message;
    }

    public static function formatResponse(ResponseInterface $response, $full = false)
    {
        $message = \sprintf(
            '%s %s HTTP/%s',
            $response->getStatusCode(),
            $response->getReasonPhrase(),
            $response->getProtocolVersion()
        );

        if ($full) {
            $message = self::appendMessage($response, $message);
        }

        return $message;
    }

    private static function appendMessage(MessageInterface $request, $message)
    {
        foreach ($request->getHeaders() as $name => $values) {
            $message .= $name . ': ' . \implode(', ', $values) . "\n";
        }

        $stream = $request->getBody();
        if (!$stream->isSeekable()) {
            // Do not read the stream
            $message .= "\n";
        } else {
            $message .= "\n" . \mb_substr($stream->__toString(), 0, 1000);
            $stream->rewind();
        }

        return $message;
    }

    public static function curlCommand(RequestInterface $request)
    {
        $command = \sprintf('curl %s', \escapeshellarg((string) $request->getUri()->withFragment('')));
        if ('1.0' === $request->getProtocolVersion()) {
            $command .= ' --http1.0';
        } elseif ('2.0' === $request->getProtocolVersion()) {
            $command .= ' --http2';
        }
        $method = \strtoupper($request->getMethod());
        if ('HEAD' === $method) {
            $command .= ' --head';
        } elseif ('GET' !== $method) {
            $command .= ' --request ' . $method;
        }

        foreach ($request->getHeaders() as $name => $values) {
            if ('host' === \strtolower($name) && $values[0] === $request->getUri()->getHost()) {
                continue;
            }
            if ('user-agent' === \strtolower($name)) {
                $command .= \sprintf(' -A %s', \escapeshellarg($values[0]));
                continue;
            }
            $command .= \sprintf(' -H %s', \escapeshellarg($name . ': ' . $request->getHeaderLine($name)));
        }

        $body = $request->getBody();
        if ($body->getSize() > 0) {
            if ($body->isSeekable()) {
                $data = $body->__toString();
                $body->rewind();
                if (\preg_match('/[\x00-\x1F\x7F]/', $data)) {
                    $data = '[binary stream omitted]';
                }
            } else {
                $data = '[non-seekable stream omitted]';
            }
            $escapedData = @\escapeshellarg($data);
            if (empty($escapedData)) {
                $escapedData = 'We couldn\'t not escape the data properly';
            }
            $command .= \sprintf(' --data %s', $escapedData);
        }

        return $command;
    }

    /**
     * {@inheritdoc}
     */
    public function process(RequestInterface $request, RequestHandlerInterface $handler): PromiseInterface
    {
        $start = \microtime(true);
        $this->logger->info(\sprintf("Sending request:\n%s", self::formatRequest($request)), ['request' => $request]);

        return $handler->handle($request)->then(function (ResponseInterface $response) use ($request, $start) {
            $milliseconds = (int) \round((\microtime(true) - $start) * 1000);
            $this->logger->info(
                \sprintf("Received response:\n%s\n\nfor request:\n%s", self::formatResponse($response),
                    self::formatRequest($request)),
                [
                    'request' => $request,
                    'response' => $response,
                    'milliseconds' => $milliseconds,
                ]
            );

            return $response;
        }, function (ClientException $exception) use ($request, $start) {
            $milliseconds = (int) \round((\microtime(true) - $start) * 1000);
            if ($exception instanceof HttpException) {
                $this->logger->error(
                    \sprintf("Error:\n%s\nwith response:\n%s\n\nwhen sending request:\n%s", $exception->getMessage(),
                        self::formatResponse($exception->getResponse()), self::formatRequest($request)),
                    [
                        'request' => $request,
                        'response' => $exception->getResponse(),
                        'exception' => $exception,
                        'milliseconds' => $milliseconds,
                    ]
                );
            } else {
                $this->logger->error(
                    \sprintf("Error:\n%s\nwhen sending request:\n%s", $exception->getMessage(),
                        self::formatRequest($request)),
                    [
                        'request' => $request,
                        'exception' => $exception,
                        'milliseconds' => $milliseconds,
                    ]
                );
            }

            throw $exception;
        });
    }
}