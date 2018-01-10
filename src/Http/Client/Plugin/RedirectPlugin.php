<?php

namespace Hail\Http\Client\Plugin;

use Hail\Http\Client\Exception\CircularRedirectionException;
use Hail\Http\Client\Exception\MultipleRedirectionException;
use Hail\Http\Client\Exception\HttpException;
use Hail\Http\Client\RequestHandlerInterface;
use Hail\Promise\PromiseInterface;
use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;

/**
 * Follow redirections.
 *
 * @author Joel Wurtz <joel.wurtz@gmail.com>
 */
class RedirectPlugin implements PluginInterface
{
    /**
     * Rule on how to redirect, change method for the new request.
     *
     * @var array
     */
    protected const REDIRECT_CODES = [
        300 => [
            'switch' => [
                'unless' => ['GET', 'HEAD'],
                'to' => 'GET',
            ],
            'multiple' => true,
            'permanent' => false,
        ],
        301 => [
            'switch' => [
                'unless' => ['GET', 'HEAD'],
                'to' => 'GET',
            ],
            'multiple' => false,
            'permanent' => true,
        ],
        302 => [
            'switch' => [
                'unless' => ['GET', 'HEAD'],
                'to' => 'GET',
            ],
            'multiple' => false,
            'permanent' => false,
        ],
        303 => [
            'switch' => [
                'unless' => ['GET', 'HEAD'],
                'to' => 'GET',
            ],
            'multiple' => false,
            'permanent' => false,
        ],
        307 => [
            'switch' => false,
            'multiple' => false,
            'permanent' => false,
        ],
        308 => [
            'switch' => false,
            'multiple' => false,
            'permanent' => true,
        ],
    ];

    /**
     * Determine how header should be preserved from old request.
     *
     * @var bool|array
     *
     * true     will keep all previous headers (default value)
     * false    will ditch all previous headers
     * string[] will keep only headers with the specified names
     */
    protected $preserveHeader = true;

    /**
     * Store all previous redirect from 301 / 308 status code.
     *
     * @var array
     */
    protected $redirectStorage = [];

    /**
     * Whether the location header must be directly used for a multiple redirection status code (300).
     *
     * @var bool
     */
    protected $useDefaultForMultiple = true;

    /**
     * @var array
     */
    protected $circularDetection = [];

    /**
     * @param array $config {
     *
     *     @var bool|string[] $preserve_header True keeps all headers, false remove all of them, an array is interpreted as a list of header names to keep
     *     @var bool $use_default_for_multiple Whether the location header must be directly used for a multiple redirection status code (300).
     * }
     */
    public function __construct(array $config = [])
    {
        if (isset($config['preserve_header'])) {
            if ($config['preserve_header'] === false) {
                $this->preserveHeader = [];
            } elseif (\is_array($config['preserve_header'])) {
                $this->preserveHeader = $config['preserve_header'];
            }
        }

        if (isset($config['use_default_for_multiple'])) {
            $this->useDefaultForMultiple = (bool) $config['use_default_for_multiple'];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function process(RequestInterface $request, RequestHandlerInterface $handler): PromiseInterface
    {
        // Check in storage
        if (\array_key_exists((string) $request->getUri(), $this->redirectStorage)) {
            $uri = $this->redirectStorage[(string) $request->getUri()]['uri'];
            $statusCode = $this->redirectStorage[(string) $request->getUri()]['status'];
            $redirectRequest = $this->buildRedirectRequest($request, $uri, $statusCode);

            return $handler->restart($redirectRequest);
        }

        return $handler->handle($request)->then(function (ResponseInterface $response) use ($request, $handler) {
            $statusCode = $response->getStatusCode();

            if (!isset(self::REDIRECT_CODES[$statusCode])) {
                return $response;
            }

            $uri = $this->createUri($response, $request);
            $redirectRequest = $this->buildRedirectRequest($request, $uri, $statusCode);
            $chainIdentifier = \spl_object_hash($handler);

            if (!isset($this->circularDetection[$chainIdentifier])) {
                $this->circularDetection[$chainIdentifier] = [];
            }

            $this->circularDetection[$chainIdentifier][] = (string) $request->getUri();

            if (\in_array((string) $redirectRequest->getUri(), $this->circularDetection[$chainIdentifier], true)) {
                throw new CircularRedirectionException('Circular redirection detected', $request, $response);
            }

            if (self::REDIRECT_CODES[$statusCode]['permanent']) {
                $this->redirectStorage[(string) $request->getUri()] = [
                    'uri' => $uri,
                    'status' => $statusCode,
                ];
            }

            // Call redirect request in synchrone
            $redirectPromise = $handler->restart($redirectRequest);

            return $redirectPromise->wait();
        });
    }

    /**
     * Builds the redirect request.
     *
     * @param RequestInterface $request    Original request
     * @param UriInterface     $uri        New uri
     * @param int              $statusCode Status code from the redirect response
     *
     * @return MessageInterface|RequestInterface
     */
    protected function buildRedirectRequest(RequestInterface $request, UriInterface $uri, $statusCode)
    {
        $request = $request->withUri($uri);

        if (false !== self::REDIRECT_CODES[$statusCode]['switch'] && !\in_array($request->getMethod(), self::REDIRECT_CODES[$statusCode]['switch']['unless'], true)) {
            $request = $request->withMethod(self::REDIRECT_CODES[$statusCode]['switch']['to']);
        }

        if (\is_array($this->preserveHeader)) {
            $headers = \array_keys($request->getHeaders());

            foreach ($headers as $name) {
                if (!\in_array($name, $this->preserveHeader, true)) {
                    $request = $request->withoutHeader($name);
                }
            }
        }

        return $request;
    }

    /**
     * Creates a new Uri from the old request and the location header.
     *
     * @param ResponseInterface $response The redirect response
     * @param RequestInterface  $request  The original request
     *
     * @throws HttpException                If location header is not usable (missing or incorrect)
     * @throws MultipleRedirectionException If a 300 status code is received and default location cannot be resolved (doesn't use the location header or not present)
     *
     * @return UriInterface
     */
    private function createUri(ResponseInterface $response, RequestInterface $request)
    {
        if (self::REDIRECT_CODES[$response->getStatusCode()]['multiple'] && (!$this->useDefaultForMultiple || !$response->hasHeader('Location'))) {
            throw new MultipleRedirectionException('Cannot choose a redirection', $request, $response);
        }

        if (!$response->hasHeader('Location')) {
            throw new HttpException('Redirect status code, but no location header present in the response', $request, $response);
        }

        $location = $response->getHeaderLine('Location');
        $parsedLocation = \parse_url($location);

        if (false === $parsedLocation) {
            throw new HttpException(sprintf('Location %s could not be parsed', $location), $request, $response);
        }

        $uri = $request->getUri();

        if (\array_key_exists('scheme', $parsedLocation)) {
            $uri = $uri->withScheme($parsedLocation['scheme']);
        }

        if (\array_key_exists('host', $parsedLocation)) {
            $uri = $uri->withHost($parsedLocation['host']);
        }

        if (\array_key_exists('port', $parsedLocation)) {
            $uri = $uri->withPort($parsedLocation['port']);
        }

        if (\array_key_exists('path', $parsedLocation)) {
            $uri = $uri->withPath($parsedLocation['path']);
        }

        if (\array_key_exists('query', $parsedLocation)) {
            $uri = $uri->withQuery($parsedLocation['query']);
        } else {
            $uri = $uri->withQuery('');
        }

        if (\array_key_exists('fragment', $parsedLocation)) {
            $uri = $uri->withFragment($parsedLocation['fragment']);
        } else {
            $uri = $uri->withFragment('');
        }

        return $uri;
    }
}
