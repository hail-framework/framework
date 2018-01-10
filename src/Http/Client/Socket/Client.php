<?php

namespace Hail\Http\Client\Socket;

use Hail\Http\Client\Polyfill\AsyncRequestTrait;
use Hail\Http\Client\Socket\Exception\BrokenPipeException;
use Hail\Http\Client\Socket\Exception\ConnectionException;
use Hail\Http\Client\Socket\Exception\InvalidRequestException;
use Hail\Http\Client\Socket\Exception\SSLConnectionException;
use Hail\Http\Client\Socket\Exception\TimeoutException;
use Hail\Http\Factory;
use Hail\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Socket Http Client.
 *
 * Use stream and socket capabilities of the core of PHP to send HTTP requests
 *
 * @author Joel Wurtz <joel.wurtz@gmail.com>
 */
class Client implements ClientInterface
{
    use AsyncRequestTrait;

    private $config;

    /**
     * Constructor.
     *
     * @param array $config                 {
     *
     * @var string  $remote_socket          Remote entrypoint (can be a tcp or unix domain address)
     * @var int     $timeout                Timeout before canceling request
     * @var array   $stream_context_options Context options as defined in the PHP documentation
     * @var array   $stream_context_param   Context params as defined in the PHP documentation
     * @var bool    $ssl                    Use ssl, default to scheme from request, false if not present
     * @var int     $write_buffer_size      Buffer when writing the request body, defaults to 8192
     * @var int     $ssl_method             Crypto method for ssl/tls, see PHP doc, defaults to STREAM_CRYPTO_METHOD_TLS_CLIENT
     * }
     */
    public function __construct(array $config = [])
    {
        $this->config = $this->configure($config);
    }

    /**
     * {@inheritdoc}
     */
    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $remote = $this->config['remote_socket'];
        $useSsl = $this->config['ssl'];

        if (!$request->hasHeader('Connection')) {
            $request = $request->withHeader('Connection', 'close');
        }

        if (null === $remote) {
            $remote = $this->determineRemoteFromRequest($request);
        }

        if (null === $useSsl) {
            $useSsl = ('https' === $request->getUri()->getScheme());
        }

        $socket = $this->createSocket($request, $remote, $useSsl);

        try {
            $this->writeRequest($socket, $request, $this->config['write_buffer_size']);
            $response = $this->readResponse($request, $socket);
        } catch (\Exception $e) {
            $this->closeSocket($socket);

            throw $e;
        }

        return $response;
    }

    /**
     * Create the socket to write request and read response on it.
     *
     * @param RequestInterface $request Request for
     * @param string           $remote  Entrypoint for the connection
     * @param bool             $useSsl  Whether to use ssl or not
     *
     * @throws ConnectionException|SSLConnectionException When the connection fail
     *
     * @return resource Socket resource
     */
    protected function createSocket(RequestInterface $request, $remote, $useSsl): resource
    {
        $errNo = null;
        $errMsg = null;
        $socket = @\stream_socket_client($remote, $errNo, $errMsg, \floor($this->config['timeout'] / 1000),
            STREAM_CLIENT_CONNECT, $this->config['stream_context']);

        if (false === $socket) {
            throw new ConnectionException($errMsg, $request);
        }

        \stream_set_timeout($socket, \floor($this->config['timeout'] / 1000), $this->config['timeout'] % 1000);

        if ($useSsl && false === @\stream_socket_enable_crypto($socket, true, $this->config['ssl_method'])) {
            throw new SSLConnectionException(\sprintf('Cannot enable tls: %s', \error_get_last()['message']), $request);
        }

        return $socket;
    }

    /**
     * Close the socket, used when having an error.
     *
     * @param resource $socket
     */
    protected function closeSocket($socket): void
    {
        \fclose($socket);
    }

    /**
     * Return configuration for the socket client.
     *
     * @param array $config Configuration from user
     *
     * @return array Configuration resolved
     */
    protected function configure(array $config = []): array
    {
        $default = [
            'remote_socket' => null,
            'timeout' => \ini_get('default_socket_timeout') * 1000,
            'stream_context_options' => [],
            'stream_context_param' => [],
            'ssl' => null,
            'write_buffer_size' => 8192,
            'ssl_method' => STREAM_CRYPTO_METHOD_TLS_CLIENT,
        ];

        $config += $default;

        if (!\is_array($config['stream_context_options'])) {
            $config['stream_context_options'] = [];
        }

        if (!\is_array($config['stream_context_param'])) {
            $config['stream_context_param'] = [];
        }

        if (!isset($config['stream_context']) || !\is_resource($config['stream_context'])) {
            $config['stream_context'] = \stream_context_create(
                $config['stream_context_options'],
                $config['stream_context_param']
            );
        }

        if ($config['ssl'] !== null && !\is_bool($config['ssl'])) {
            $config['ssl'] = null;
        }

        return $config;
    }

    /**
     * Return remote socket from the request.
     *
     * @param RequestInterface $request
     *
     * @throws InvalidRequestException When no remote can be determined from the request
     *
     * @return string
     */
    private function determineRemoteFromRequest(RequestInterface $request): string
    {
        if (!$request->hasHeader('Host') && '' === $request->getUri()->getHost()) {
            throw new InvalidRequestException('Remote is not defined and we cannot determine a connection endpoint for this request (no Host header)',
                $request);
        }

        $host = $request->getUri()->getHost();
        $port = $request->getUri()->getPort() ?: ('https' === $request->getUri()->getScheme() ? 443 : 80);
        $endpoint = "$host:$port";

        // If use the host header if present for the endpoint
        if (empty($host) && $request->hasHeader('Host')) {
            $endpoint = $request->getHeaderLine('Host');
        }

        return "tcp://$endpoint";
    }

    /**
     * Write a request to a socket.
     *
     * @param resource         $socket
     * @param RequestInterface $request
     * @param int              $bufferSize
     *
     * @throws BrokenPipeException
     */
    protected function writeRequest($socket, RequestInterface $request, $bufferSize = 8192): void
    {
        if (false === $this->fwrite($socket, $this->transformRequestHeadersToString($request))) {
            throw new BrokenPipeException('Failed to send request, underlying socket not accessible, (BROKEN EPIPE)',
                $request);
        }

        if ($request->getBody()->isReadable()) {
            $this->writeBody($socket, $request, $bufferSize);
        }
    }

    /**
     * Write Body of the request.
     *
     * @param resource         $socket
     * @param RequestInterface $request
     * @param int              $bufferSize
     *
     * @throws BrokenPipeException
     */
    protected function writeBody($socket, RequestInterface $request, $bufferSize = 8192): void
    {
        $body = $request->getBody();

        if ($body->isSeekable()) {
            $body->rewind();
        }

        while (!$body->eof()) {
            $buffer = $body->read($bufferSize);

            if (false === $this->fwrite($socket, $buffer)) {
                throw new BrokenPipeException('An error occur when writing request to client (BROKEN EPIPE)', $request);
            }
        }
    }

    /**
     * Produce the header of request as a string based on a PSR Request.
     *
     * @param RequestInterface $request
     *
     * @return string
     */
    protected function transformRequestHeadersToString(RequestInterface $request): string
    {
        $message = \strtoupper($request->getMethod()) . ' ' . $request->getRequestTarget() .
            ' HTTP/' . $request->getProtocolVersion() . "\r\n";

        foreach ($request->getHeaders() as $name => $values) {
            $message .= $name . ': ' . \implode(', ', $values) . "\r\n";
        }

        $message .= "\r\n";

        return $message;
    }

    /**
     * Replace fwrite behavior as api is broken in PHP.
     *
     * @see https://secure.phabricator.com/rPHU69490c53c9c2ef2002bc2dd4cecfe9a4b080b497
     *
     * @param resource $stream The stream resource
     * @param string   $bytes  Bytes written in the stream
     *
     * @return bool|int false if pipe is broken, number of bytes written otherwise
     */
    private function fwrite(resource $stream, string $bytes)
    {
        if ('' === $bytes) {
            return 0;
        }
        $result = @\fwrite($stream, $bytes);
        if (0 !== $result) {
            // In cases where some bytes are witten (`$result > 0`) or
            // an error occurs (`$result === false`), the behavior of fwrite() is
            // correct. We can return the value as-is.
            return $result;
        }
        // If we make it here, we performed a 0-length write. Try to distinguish
        // between EAGAIN and EPIPE. To do this, we're going to `stream_select()`
        // the stream, write to it again if PHP claims that it's writable, and
        // consider the pipe broken if the write fails.
        $read = [];
        $write = [$stream];
        $except = [];
        @\stream_select($read, $write, $except, 0);
        if (!$write) {
            // The stream isn't writable, so we conclude that it probably really is
            // blocked and the underlying error was EAGAIN. Return 0 to indicate that
            // no data could be written yet.
            return 0;
        }
        // If we make it here, PHP **just** claimed that this stream is writable, so
        // perform a write. If the write also fails, conclude that these failures are
        // EPIPE or some other permanent failure.
        $result = @\fwrite($stream, $bytes);
        if (0 !== $result) {
            // The write worked or failed explicitly. This value is fine to return.
            return $result;
        }
        // We performed a 0-length write, were told that the stream was writable, and
        // then immediately performed another 0-length write. Conclude that the pipe
        // is broken and return `false`.
        return false;
    }

    /**
     * Read a response from a socket.
     *
     * @param RequestInterface $request
     * @param resource         $socket
     *
     * @throws TimeoutException    When the socket timed out
     * @throws BrokenPipeException When the response cannot be read
     *
     * @return ResponseInterface
     */
    protected function readResponse(RequestInterface $request, $socket): ResponseInterface
    {
        $headers = [];
        $reason = null;

        while (false !== ($line = \fgets($socket))) {
            if ('' === \rtrim($line)) {
                break;
            }
            $headers[] = \trim($line);
        }

        $metadatas = \stream_get_meta_data($socket);

        if (isset($metadatas['timed_out']) && true === $metadatas['timed_out']) {
            throw new TimeoutException('Error while reading response, stream timed out', null, null, $request);
        }

        $parts = \explode(' ', $headers[0], 3);
        unset($headers[0]);

        if (\count($parts) <= 1) {
            throw new BrokenPipeException('Cannot read the response', $request);
        }

        $protocol = \substr($parts[0], -3);
        $status = $parts[1];

        if (isset($parts[2])) {
            $reason = $parts[2];
        }

        // Set the size on the stream if it was returned in the response
        $responseHeaders = [];

        foreach ($headers as $header) {
            $headerParts = \explode(':', $header, 2);

            if (!\array_key_exists(trim($headerParts[0]), $responseHeaders)) {
                $responseHeaders[trim($headerParts[0])] = [];
            }

            $responseHeaders[\trim($headerParts[0])][] = isset($headerParts[1])
                ? \trim($headerParts[1])
                : '';
        }

        $response = Factory::response($status, null, $responseHeaders, $protocol, $reason);
        $stream = Factory::stream($socket);

        return $response->withBody($stream);
    }
}
