<?php
/*
 * This class some code from the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 */

declare(strict_types=1);

namespace Hail\Http;

use Hail\Application;
use Hail\Exception\ActionForward;
use Hail\Exception\ActionError;
use Hail\Util\Json;
use Hail\Util\MimeType;
use Hail\Exception\BadRequestException;
use Hail\Util\Exception\JsonException;
use Hail\Http\Message\Response as ResponseMessage;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;

/**
 * Response Builder
 *
 * @package Hail
 */
class Response
{
    /**
     * @var Application
     */
    protected $app;

    /**
     * @var Request
     */
    protected $request;

    /**
     * @var Header
     */
    public $header;

    /**
     * @var Cookie
     */
    public $cookie;

    /**
     * @var string
     */
    protected $output;

    /**
     * @var int
     */
    protected $status;

    /**
     * @var string
     */
    protected $reason;

    /**
     * @var string
     */
    protected $version;

    /**
     * @var string
     */
    protected $emitter;

    /**
     * Response constructor.
     *
     * @param Application $app
     * @param Request $request
     *
     * @throws \InvalidArgumentException
     */
    public function __construct(Application $app, Request $request)
    {
        $this->app = $app;
        $this->request = $request;

        $this->cookie = new Cookie(
            $app->config('cookie')
        );
        $this->header = new Header();

        $this->setStatus(200);
    }

    public function reset()
    {
        $this->cookie->reset();
        $this->header->replace();

        $this->output = $this->reason = $this->version = null;

        $this->setStatus(200);
    }

    /**
     * @return int|null
     */
    public function getStatus(): ?int
    {
        return $this->status;
    }

    /**
     * @param int $code
     * @param string|null $reason
     *
     * @return self
     * @throws \InvalidArgumentException
     */
    public function setStatus(int $code, string $reason = null): self
    {
        if (!isset(ResponseMessage::$phrases[$code])) {
            throw new \InvalidArgumentException("The HTTP status code is not valid: {$code}");
        }

        $this->status = $code;

        if ($reason === null) {
            $reason = ResponseMessage::$phrases[$code];
        }
        $this->reason = $reason;

        return $this;
    }

    /**
     * @return null|string
     */
    public function getReason(): ?string
    {
        return $this->reason;
    }

    /**
     * @param string $phrase
     *
     * @return self
     */
    public function setReason(string $phrase): self
    {
        $this->reason = $phrase;

        return $this;
    }

    /**
     * @return null|string
     */
    public function getVersion(): ?string
    {
        return $this->version;
    }

    /**
     * Protocol version
     *
     * @param string $version
     *
     * @return self
     */
    public function setVersion(string $version): self
    {
        $this->version = $version;

        return $this;
    }

    /**
     * @param string|null $name
     *
     * @return Response
     */
    public function to(string $name = null): self
    {
        if ($name === null) {
            $handler = $this->app->handler();
            $app = isset($handler['app']) ? '.' . $handler['app'] : '';
            $name = $this->app->config('app.output' . $app);
        }

        if (!\in_array($name, ['json', 'template', 'html', 'text', 'redirect', 'file', 'empty'], true)) {
            throw new \InvalidArgumentException('Output type not defined: ' . $name);
        }

        $this->output = $name;

        return $this;
    }

    /**
     * @param int $status
     *
     * @return ResponseInterface
     */
    public function empty(int $status = 204)
    {
        return $this->setStatus($status)->response();
    }

    /**
     * @param string|UriInterface $uri
     * @param int                 $status
     *
     * @return ResponseInterface
     * @throws \InvalidArgumentException
     */
    public function redirect($uri, int $status = 301): ResponseInterface
    {
        if (!\is_string($uri) && !$uri instanceof UriInterface) {
            throw new \InvalidArgumentException('Uri MUST be a string or Psr\Http\Message\UriInterface instance; received "' .
                (\is_object($uri) ? \get_class($uri) : \gettype($uri)) . '"');
        }

        $this->header->set('Location', (string) $uri);

        return $this->empty($status);
    }

    /**
     * @return ResponseInterface
     */
    public function notModified(): ResponseInterface
    {
        // remove headers that MUST NOT be included with 304 Not Modified responses
        foreach (
            [
                'Allow',
                'Content-Encoding',
                'Content-Language',
                'Content-Length',
                'Content-MD5',
                'Content-Type',
                'Last-Modified',
            ] as $header
        ) {
            $this->header->remove($header);
        }

        return $this->empty(304);
    }

    /**
     * Get or set template name
     *
     * @param string|array|null $name
     * @param array $params
     *
     * @return ResponseInterface
     *
     * @throws \InvalidArgumentException
     * @throws \LogicException
     */
    public function template($name, array $params = []): ResponseInterface
    {
        if (\is_array($name)) {
            $params = $name;
            $name = null;
        }

        if ($name === null) {
            $handler = $this->app->handler();
            if ($handler instanceof \Closure) {
                throw new \LogicException('Con not build the template from closure handler!');
            }

            $name = \ltrim($handler['app'] . '/' . $handler['controller'] . '/' . $handler['action'], '/');
        } elseif (!\is_string($name)) {
            throw new \InvalidArgumentException('Template name not found!');
        }

        $response = $this->response();

        return $this->app->render($response, $name, $params);
    }

    /**
     * @param      $data
     * @param bool $strict
     *
     * @return ResponseInterface
     * @throws \InvalidArgumentException
     * @throws JsonException
     */
    public function json($data, $strict = false): ResponseInterface
    {
        if (\is_resource($data)) {
            throw new \InvalidArgumentException('Cannot JSON encode resources');
        }

        if (\is_array($data) && !isset($data['ret'])) {
            $data['ret'] = 0;
            $data['msg'] = '';
        }

        $data = Json::encode($data,
            JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES
        );

        return $this->print($data, $strict ? 'application/json' : null);
    }

    /**
     * @param string $text
     * @param bool $strict
     *
     * @return ResponseInterface
     */
    public function text(string $text, $strict = false): ResponseInterface
    {
        return $this->print($text, $strict ? 'text/plain; charset=utf-8' : null);
    }

    /**
     * @param string $html
     * @param bool $strict
     *
     * @return ResponseInterface
     */
    public function html(string $html, $strict = false): ResponseInterface
    {
        return $this->print($html, $strict ? 'text/html; charset=utf-8' : null);
    }

    /**
     * @param string $str
     * @param string $contentType
     *
     * @return ResponseInterface
     */
    public function print(
        string $str,
        string $contentType = null
    ): ResponseInterface
    {
        $body = Factory::streamFromFile('php://temp', 'wb+');
        $body->write($str);
        $body->rewind();

        if ($contentType) {
            $this->header->set('Content-Type', $contentType);
        }

        return $this->setStatus(200)->response($body);
    }

    /**
     * @param string $file
     * @param null $name
     * @param bool $download
     *
     * @return ResponseInterface
     * @throws \LogicException
     */
    public function file(string $file, $name = null, $download = true): ResponseInterface
    {
        if (!\is_file($file)) {
            throw new \LogicException("File '$file' doesn't exist.");
        }

        $extension = \substr($file, \strrpos($file, '.') + 1);
        $this->header->set('Content-Type',
            MimeType::getMimeType($extension) ?? 'application/octet-stream'
        );

        $disposition = $download ? Header::DISPOSITION_ATTACHMENT : Header::DISPOSITION_INLINE;
        $this->header->set('Content-Disposition',
            $this->header->makeDisposition($disposition, \basename($file), $name)
        );

        $size = $length = \filesize($file);

        $this->header->set('Accept-Ranges', 'bytes');
        if (\preg_match('#^bytes=(\d*)-(\d*)\z#', $this->request->header('Range'), $matches)) {
            list(, $start, $end) = $matches;

            if ($start === '') {
                $start = \max(0, $size - $end);
                $end = $size - 1;
            } elseif ($end === '' || $end > $size - 1) {
                $end = $size - 1;
            }

            if ($end < $start) {
                return $this->empty(416); // requested range not satisfiable
            }

            $this->setStatus(206);
            $this->header->set('Content-Range', 'bytes ' . $start . '-' . $end . '/' . $size);
            $length = $end - $start + 1;
        } else {
            $this->header->set('Content-Range', 'bytes 0-' . ($size - 1) . '/' . $size);
        }

        $this->header->set('Content-Length', $length);

        $this->app->emitter(
            $this->emitter ?? Emitter\SapiStream::class
        );

        return $this->response(
            Factory::streamFromFile($file, 'rb')
        );
    }

    /**
     * @param null $body
     *
     * @return ResponseInterface
     */
    public function response($body = null): ResponseInterface
    {
        /* RFC2616 - 14.18 says all Responses need to have a Date */
        if (!$this->header->has('Date')) {
            $this->setDate(\DateTime::createFromFormat('U', (string) \time()));
        }

        if (($this->status >= 100 && $this->status < 200) || $this->status === 204 || $this->status === 304) {
            $body = null;
            $this->header->remove('Content-Type');
            $this->header->remove('Content-Length');
        } else {
            if ($this->header->has('Transfer-Encoding')) {
                $this->header->remove('Content-Length');
            }

            if ($this->request->method() === 'HEAD') {
                $body = null;
            }
        }

        if (!$this->version && 'HTTP/1.0' !== $this->request->server('SERVER_PROTOCOL')) {
            $this->version = '1.1';
        }

        // Check if we need to send extra expire info headers
        if ('1.0' === $this->version && false !== strpos($this->getHeaderLine('Cache-Control') ?? '', 'no-cache')) {
            $this->header->set('Pragma', 'no-cache');
            $this->header->set('Expires', -1);
        }

        // Checks if we need to remove Cache-Control for SSL encrypted downloads when using IE < 9.
        if (
            true === $this->request->secure() &&
            false !== \stripos($this->getHeaderLine('Content-Disposition') ?? '', 'attachment') &&
            \preg_match('/MSIE (.*?);/i', $this->request->header('User-Agent'), $match) === 1
        ) {
            if ((int) \preg_replace('/(MSIE )(.*?);/', '$2', $match[0]) < 9) {
                $this->header->remove('Cache-Control');
            }
        }

        $headers = $this->header->all();
        $this->cookie->inject($headers);

        return Factory::response($this->status, $body, $headers, $this->version, $this->reason);
    }

    /**
     * @param mixed $return
     *
     * @return ResponseInterface
     */
    public function default($return): ResponseInterface
    {
        if ($this->output === null) {
            $this->to();
        }

        switch ($this->output) {
            case 'json':
                if ($return === true) {
                    $return = [];
                }

                return $this->json($return);

            case 'text':
                return $this->text($return);

            case 'html':
                return $this->html($return);

            case 'template':
                return $this->template(null, $return);

            case 'redirect':
                return $this->redirect($return);

            case 'file':
                return $this->file($return);

            default:
                return $this->empty();
        }
    }

    /**
     * @param array $to
     * @param bool $return
     *
     * @return \Psr\Http\Message\ResponseInterface
     * @throws BadRequestException
     * @throws ActionForward
     */
    public function forward(array $to, $return = false): ResponseInterface
    {
        if ($return) {
            $this->app->params($to['params'] ?? null);

            return $this->app->handle(
                $this->app->handler($to)
            );
        }

        $forward = new ActionForward();
        $forward->setForwardTo($to);

        throw $forward;
    }

    /**
     * @noinspection PhpDocMissingThrowsInspection
     *
     * @param int $code
     * @param string $msg
     * @param array ...$args
     */
    public function error(int $code = 0, string $msg = null, ...$args): void
    {
        if ($msg !== null && $args !== []) {
            $msg = \sprintf($msg, ...$args);
        }

        /* @noinspection PhpUnhandledExceptionInspection */
        throw new ActionError($msg, $code);
    }

    /**
     * @param string $msg
     * @param int $code
     *
     * @throws ActionError
     */
    public function exception(string $msg = '', int $code = -1)
    {
        throw new ActionError($msg, $code);
    }

    /**
     * @param string $name
     * @param string $value
     * @param int $time
     *
     * @return Response
     */
    public function setCookie(string $name, string $value, $time = 0): self
    {
        $this->cookie->set($name, $value, $time);

        return $this;
    }

    /**
     * @return array
     */
    public function getHeaders(): array
    {
        return $this->header->all();
    }

    /**
     * @param array $headers
     *
     * @return Response
     * @throws \LogicException
     */
    public function setHeaders(array $headers): self
    {
        $this->header->replace($headers);

        return $this;
    }

    /**
     * @param array $headers
     *
     * @return Response
     * @throws \LogicException
     */
    public function addHeaders(array $headers): self
    {
        $this->header->add($headers);

        return $this;
    }

    /**
     * @param string $header
     *
     * @return array
     */
    public function getHeader(string $header): array
    {
        return $this->header->get($header);
    }

    /**
     * @param string $header
     *
     * @return string|null
     */
    public function getHeaderLine(string $header): ?string
    {
        $headers = $this->header->get($header);

        return $headers === [] ? null : \implode(', ', $headers);
    }

    /**
     * @param string $header
     * @param        $value
     *
     * @return Response
     * @throws \LogicException
     */
    public function setHeader(string $header, $value): self
    {
        $this->header->set($header, $value);

        return $this;
    }

    /**
     * @param string $header
     * @param        $value
     *
     * @return Response
     * @throws \LogicException
     */
    public function addHeader(string $header, $value): self
    {
        $this->header->set($header, $value, false);

        return $this;
    }

    /**
     * Returns the Date header as a DateTime instance.
     *
     * @return \DateTime A \DateTime instance
     *
     * @throws \RuntimeException When the header is not parseable
     */
    public function getDate(): \DateTime
    {
        /*
            RFC2616 - 14.18 says all Responses need to have a Date.
            Make sure we provide one even if it the header
            has been removed in the meantime.
         */
        if (!$this->header->has('Date')) {
            $this->setDate(\DateTime::createFromFormat('U', (string) \time()));
        }

        return $this->header->getDate('Date');
    }

    /**
     * Sets the Date header.
     *
     * @param \DateTime $date A \DateTime instance
     *
     * @return $this
     */
    public function setDate(\DateTime $date): self
    {
        $date->setTimezone(new \DateTimeZone('UTC'));
        $this->header->set('Date', $date->format('D, d M Y H:i:s') . ' GMT');

        return $this;
    }

    /**
     * Sends HTTP headers.
     *
     * @return $this
     */
    public function sendHeaders(): self
    {
        // headers have already been sent by the developer
        if (\headers_sent()) {
            return $this;
        }

        /* RFC2616 - 14.18 says all Responses need to have a Date */
        if (!$this->header->has('Date')) {
            $this->setDate(\DateTime::createFromFormat('U', (string) \time()));
        }

        // status
        \header('HTTP/' . $this->version . ' ' . $this->status . ' ' . $this->reason, true, $this->status);

        // headers
        $headers = $this->header->all();
        $this->cookie->inject($headers);

        foreach ($this->header->all() as $name => $values) {
            $first = true;
            foreach ($values as $value) {
                \header($name . ': ' . $value, $first, $this->status);
                $first = false;
            }
        }

        return $this;
    }

    /**
     * Marks the response as "private".
     *
     * It makes the response ineligible for serving other clients.
     *
     * @return $this
     */
    public function setPrivate(): self
    {
        $this->header->removeCacheControlDirective('public');
        $this->header->addCacheControlDirective('private');

        return $this;
    }

    /**
     * Marks the response as "public".
     *
     * It makes the response eligible for serving other clients.
     *
     * @return $this
     */
    public function setPublic(): self
    {
        $this->header->addCacheControlDirective('public');
        $this->header->removeCacheControlDirective('private');

        return $this;
    }

    /**
     * Returns the age of the response.
     *
     * @return int The age of the response in seconds
     */
    public function getAge(): int
    {
        if (null !== $age = $this->getHeaderLine('Age')) {
            return (int) $age;
        }

        return \max(\time() - $this->getDate()->format('U'), 0);
    }

    /**
     * Marks the response stale by setting the Age header to be equal to the maximum age of the response.
     *
     * @return $this
     */
    public function expire(): self
    {
        if ($this->getTtl() > 0) {
            $this->header->set('Age', $this->getMaxAge());
        }

        return $this;
    }

    /**
     * Returns the value of the Expires header as a DateTime instance.
     *
     * @return \DateTime|null A DateTime instance or null if the header does not exist
     */
    public function getExpires(): ?\DateTime
    {
        try {
            return $this->header->getDate('Expires');
        } catch (\RuntimeException $e) {
            // according to RFC 2616 invalid date formats (e.g. "0" and "-1") must be treated as in the past
            return \DateTime::createFromFormat(DATE_RFC2822, 'Sat, 01 Jan 00 00:00:00 +0000');
        }
    }

    /**
     * Sets the Expires HTTP header with a DateTime instance.
     *
     * Passing null as value will remove the header.
     *
     * @param \DateTime|null $date A \DateTime instance or null to remove the header
     *
     * @return $this
     */
    public function setExpires(\DateTime $date = null): self
    {
        if (null === $date) {
            $this->header->remove('Expires');
        } else {
            $date = clone $date;
            $date->setTimezone(new \DateTimeZone('UTC'));
            $this->header->set('Expires', $date->format('D, d M Y H:i:s') . ' GMT');
        }

        return $this;
    }

    /**
     * Returns the number of seconds after the time specified in the response's Date
     * header when the response should no longer be considered fresh.
     *
     * First, it checks for a s-maxage directive, then a max-age directive, and then it falls
     * back on an expires header. It returns null when no maximum age can be established.
     *
     * @return int|null Number of seconds
     */
    public function getMaxAge(): ?int
    {
        if ($this->header->hasCacheControlDirective('s-maxage')) {
            return (int) $this->header->getCacheControlDirective('s-maxage');
        }

        if ($this->header->hasCacheControlDirective('max-age')) {
            return (int) $this->header->getCacheControlDirective('max-age');
        }

        if (null !== $this->getExpires()) {
            return ((int) $this->getExpires()->format('U')) - ((int) $this->getDate()->format('U'));
        }

        return null;
    }

    /**
     * Sets the number of seconds after which the response should no longer be considered fresh.
     *
     * This methods sets the Cache-Control max-age directive.
     *
     * @param int $value Number of seconds
     *
     * @return $this
     */
    public function setMaxAge(int $value): self
    {
        $this->header->addCacheControlDirective('max-age', $value);

        return $this;
    }

    /**
     * Sets the number of seconds after which the response should no longer be considered fresh by shared caches.
     *
     * This methods sets the Cache-Control s-maxage directive.
     *
     * @param int $value Number of seconds
     *
     * @return $this
     */
    public function setSharedMaxAge(int $value): self
    {
        $this->setPublic();
        $this->header->addCacheControlDirective('s-maxage', $value);

        return $this;
    }

    /**
     * Returns the response's time-to-live in seconds.
     *
     * It returns null when no freshness information is present in the response.
     *
     * When the responses TTL is <= 0, the response may not be served from cache without first
     * revalidating with the origin.
     *
     * @return int|null The TTL in seconds
     */
    public function getTtl(): ?int
    {
        if (null !== $maxAge = $this->getMaxAge()) {
            return $maxAge - $this->getAge();
        }

        return null;
    }

    /**
     * Sets the response's time-to-live for shared caches.
     *
     * This method adjusts the Cache-Control/s-maxage directive.
     *
     * @param int $seconds Number of seconds
     *
     * @return $this
     */
    public function setTtl(int $seconds): self
    {
        $this->setSharedMaxAge($this->getAge() + $seconds);

        return $this;
    }

    /**
     * Sets the response's time-to-live for private/client caches.
     *
     * This method adjusts the Cache-Control/max-age directive.
     *
     * @param int $seconds Number of seconds
     *
     * @return $this
     */
    public function setClientTtl(int $seconds): self
    {
        $this->setMaxAge($this->getAge() + $seconds);

        return $this;
    }

    /**
     * Returns the Last-Modified HTTP header as a DateTime instance.
     *
     * @return \DateTime|null A DateTime instance or null if the header does not exist
     *
     * @throws \RuntimeException When the HTTP header is not parseable
     */
    public function getLastModified(): ?\DateTime
    {
        return $this->header->getDate('Last-Modified');
    }

    /**
     * Sets the Last-Modified HTTP header with a DateTime instance.
     *
     * Passing null as value will remove the header.
     *
     * @param \DateTime|null $date A \DateTime instance or null to remove the header
     *
     * @return $this
     */
    public function setLastModified(\DateTime $date = null): self
    {
        if (null === $date) {
            $this->header->remove('Last-Modified');
        } else {
            $date = clone $date;
            $date->setTimezone(new \DateTimeZone('UTC'));
            $this->header->set('Last-Modified', $date->format('D, d M Y H:i:s') . ' GMT');
        }

        return $this;
    }

    /**
     * Returns the literal value of the ETag HTTP header.
     *
     * @return string|null The ETag HTTP header or null if it does not exist
     */
    public function getEtag(): ?string
    {
        $etag = $this->getHeader('ETag');

        if ($etag === []) {
            return null;
        }

        return $etag[0];
    }

    /**
     * Sets the ETag value.
     *
     * @param string|null $etag The ETag unique identifier or null to remove the header
     * @param bool $weak Whether you want a weak ETag or not
     *
     * @return $this
     */
    public function setEtag(string $etag = null, bool $weak = false): self
    {
        if (null === $etag) {
            $this->header->remove('Etag');
        } else {
            if (0 !== \strpos($etag, '"')) {
                $etag = '"' . $etag . '"';
            }
            $this->header->set('ETag', (true === $weak ? 'W/' : '') . $etag);
        }

        return $this;
    }

    /**
     * Sets the response's cache headers (validation and/or expiration).
     *
     * Available options are: etag, last_modified, max_age, s_maxage, private, and public.
     *
     * @param array $options An array of cache options
     *
     * @return $this
     *
     * @throws \InvalidArgumentException
     */
    public function setCache(array $options): self
    {
        if ($diff = \array_diff(\array_keys($options),
            ['etag', 'last_modified', 'max_age', 's_maxage', 'private', 'public'])
        ) {
            throw new \InvalidArgumentException(sprintf('Response does not support the following options: "%s".',
                \implode('", "', \array_values($diff))));
        }
        if (isset($options['etag'])) {
            $this->setEtag($options['etag']);
        }
        if (isset($options['last_modified'])) {
            $this->setLastModified($options['last_modified']);
        }
        if (isset($options['max_age'])) {
            $this->setMaxAge($options['max_age']);
        }
        if (isset($options['s_maxage'])) {
            $this->setSharedMaxAge($options['s_maxage']);
        }
        if (isset($options['public'])) {
            if ($options['public']) {
                $this->setPublic();
            } else {
                $this->setPrivate();
            }
        }
        if (isset($options['private'])) {
            if ($options['private']) {
                $this->setPrivate();
            } else {
                $this->setPublic();
            }
        }

        return $this;
    }

    /**
     * @param string $emitter
     *
     * @return Response
     */
    public function setEmitter(string $emitter): self
    {
        $this->emitter = $emitter;

        return $this;
    }
}