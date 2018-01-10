<?php

namespace Hail;

use Hail\Http\{
    Client\ClientInterface,
    Factory,
    RequestMethod
};
use Hail\Promise\PromiseInterface;
use Hail\Util\{
    Json, Exception\JsonException
};
use Hail\Http\Client\Psr\ClientException;
use Psr\Http\Message\{
    RequestInterface, ResponseInterface, StreamInterface, UriInterface
};

/**
 * Class Browser
 *
 * @package Hail
 * @method PromiseInterface|ResponseInterface get($uri, array $headers = [], $body = null, $protocol = '1.1')
 * @method PromiseInterface|ResponseInterface head($uri, array $headers = [], $body = null, $protocol = '1.1')
 * @method PromiseInterface|ResponseInterface trace($uri, array $headers = [], $body = null, $protocol = '1.1')
 * @method PromiseInterface|ResponseInterface post($uri, array $headers = [], $body = null, $protocol = '1.1')
 * @method PromiseInterface|ResponseInterface put($uri, array $headers = [], $body = null, $protocol = '1.1')
 * @method PromiseInterface|ResponseInterface patch($uri, array $headers = [], $body = null, $protocol = '1.1')
 * @method PromiseInterface|ResponseInterface delete($uri, array $headers = [], $body = null, $protocol = '1.1')
 * @method PromiseInterface|ResponseInterface options($uri, array $headers = [], $body = null, $protocol = '1.1')
 * @method PromiseInterface|ResponseInterface json($uri, array $headers = [], $body = null, $protocol = '1.1')
 * @method PromiseInterface asyncGet($uri, array $headers = [], $body = null, $protocol = '1.1')
 * @method PromiseInterface asyncHead($uri, array $headers = [], $body = null, $protocol = '1.1')
 * @method PromiseInterface asyncTrace($uri, array $headers = [], $body = null, $protocol = '1.1')
 * @method PromiseInterface asyncPost($uri, array $headers = [], $body = null, $protocol = '1.1')
 * @method PromiseInterface asyncPut($uri, array $headers = [], $body = null, $protocol = '1.1')
 * @method PromiseInterface asyncPatch($uri, array $headers = [], $body = null, $protocol = '1.1')
 * @method PromiseInterface asyncDelete($uri, array $headers = [], $body = null, $protocol = '1.1')
 * @method PromiseInterface asyncOptions($uri, array $headers = [], $body = null, $protocol = '1.1')
 * @method PromiseInterface asyncJson($uri, array $headers = [], $body = null, $protocol = '1.1')
 */
class Client
{
    /**
     * @var ClientInterface
     */
    protected $client;

    /**
     * @var string
     */
    protected $method = 'GET';

    /**
     * @var bool
     */
    protected $async = false;

    /**
     * @var bool
     */
    protected $json = false;

    /**
     * @var RequestInterface
     */
    protected $request;

    public function __construct(ClientInterface $client)
    {
        $this->client = $client;
    }

    /**
     * @return self
     */
    public function async(): self
    {
        $this->async = true;

        return $this;
    }

    /**
     * @param string $method
     *
     * @return Client
     */
    public function method(string $method): self
    {
        $method = \strtoupper($method);

        if ($method === 'JSON') {
            $method = RequestMethod::POST;
            $this->json = true;
        }

        if (!\defined(RequestMethod::class . '::' . $method)) {
            throw new \InvalidArgumentException("Method `$method` is invalid");
        }

        $this->method = \strtoupper($method);

        return $this;
    }

    /**
     * @param string $url
     * @param string $content
     * @param int    $timeout
     *
     * @return string
     * @throws JsonException
     */
    public function socket(string $url, string $content, $timeout = 5)
    {
        $errNo = 0;
        $errStr = '';

        $url = \parse_url($url);
        $fp = \fsockopen($url['host'], $url['port'], $errNo, $errStr, $timeout);
        if ($fp === false) {
            return Json::encode([
                'ret' => $errNo,
                'msg' => $errStr,
            ]);
        }

        \fwrite($fp, $content . "\n");
        \stream_set_timeout($fp, $timeout);
        $return = \fgets($fp, 65535);
        \fclose($fp);

        return $return;
    }

    /**
     * @return PromiseInterface|ResponseInterface
     * @throws ClientException
     */
    public function send()
    {
        $request = $this->request;

        if (!$request instanceof RequestInterface) {
            throw new \LogicException('Reqeust not create');
        }

        $return = $this->async ?
            $this->client->sendAsyncRequest($request)
            : $this->client->sendRequest($request);

        $this->reset();

        return $return;
    }

    /**
     * @param string|UriInterface|RequestInterface $uriOrRequest
     * @param array                                $headers
     * @param null|string|array|StreamInterface    $body
     * @param string                               $protocolVersion
     *
     * @return self
     */
    public function reqeust($uriOrRequest, array $headers = [], $body = null, string $protocolVersion = '1.1'): self
    {
        if ($uriOrRequest instanceof RequestInterface) {
            $this->request = $uriOrRequest;
        } else {
            $uri = $uriOrRequest;

            if ($this->method === RequestMethod::GET && \is_array($body)) {
                $uri = Factory::uri($uri);

                \parse_str($uri->getQuery(), $queries);
                $body += $queries;

                $uri = $uri->withQuery(\http_build_query($body));
            }

            $body = $this->parseBody($body);

            $this->request = Factory::request($this->method, $uri, $headers, $body, $protocolVersion);
        }

        return $this;
    }

    /**
     * @param string $name
     * @param array  $arguments
     *
     * @return PromiseInterface|ResponseInterface
     *
     * @throws ClientException
     */
    public function __call(string $name, array $arguments)
    {
        if (\strpos($name, 'async') === 0) {
            $name = \substr($name, 5);
            $this->async();

        }

        return $this->method($name)
            ->reqeust(...$arguments)
            ->send();
    }

    /**
     * @param null|string|array|StreamInterface $body
     *
     * @return null|string|StreamInterface
     * @throws JsonException
     */
    protected function parseBody($body)
    {
        switch (true) {
            case $body === null:
                return null;

            case $this->method === RequestMethod::GET:
                return null;

            case \is_array($body):
                return $this->json ? Json::encode($body) : \http_build_query($body);

            default:
                return $body;
        }
    }

    public function getClient(): ClientInterface
    {
        return $this->client;
    }

    public function reset(): void
    {
        $this->async = $this->json = false;
        $this->method = 'GET';
        $this->request = null;
    }
}
