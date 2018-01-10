<?php

namespace Hail\Facade;

use Hail\Http;
use Hail\Http\Emitter\EmitterInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;

/**
 * Class Response
 *
 * @package Hail\Facade
 * @method static int|null getStatus()
 * @method static Http\Response setStatus(int $code, string $reason = null)
 * @method static string|null getReason()
 * @method static Http\Response setReason(string $phrase)
 * @method static string|null getVersion()
 * @method static Http\Response setVersion(string $version)
 * @method static Http\Response to(string $name = null)
 * @method static ResponseInterface empty(int $status = 204)
 * @method static ResponseInterface redirect(string | UriInterface $uri)
 * @method static ResponseInterface notModified()
 * @method static ResponseInterface template(string | array | null $name, array $params = [])
 * @method static ResponseInterface json($data, $strict = false)
 * @method static ResponseInterface text(string $text, $strict = false)
 * @method static ResponseInterface html(string $html, $strict = false)
 * @method static ResponseInterface print(string $str, string $contentType = null)
 * @method static ResponseInterface file(string $file, $name = null, $download = true)
 * @method static ResponseInterface response($body = null)
 * @method static ResponseInterface default($return)
 * @method static ResponseInterface forward(array $to)
 * @method static ResponseInterface error(int $code, $msg = null)
 * @method static Http\Response setCookie(string $name, string $value, $time = 0)
 * @method static array getHeaders()
 * @method static Http\Response setHeaders(array $headers)
 * @method static array getHeader(string $header)
 * @method static string getHeaderLine(string $header)
 * @method static Http\Response setHeader(string $header, $value)
 * @method static Http\Response addHeader(string $header, $value)
 * @method static \DateTime getDate()
 * @method static Http\Response setDate(\DateTime $date)
 * @method static Http\Response sendHeaders()
 * @method static Http\Response setPrivate()
 * @method static Http\Response setPublic()
 * @method static int getAge()
 * @method static Http\Response expire()
 * @method static \DateTime|null getExpires()
 * @method static Http\Response setExpires(\DateTime $date = null)
 * @method static int|null getMaxAge()
 * @method static Http\Response setMaxAge(int $value)
 * @method static Http\Response setSharedMaxAge(int $value)
 * @method static int|null getTtl()
 * @method static Http\Response setTtl(int $seconds)
 * @method static Http\Response setClientTtl(int $seconds)
 * @method static \DateTime|null getLastModified()
 * @method static Http\Response setLastModified(\DateTime $date = null)
 * @method static string|null getEtag()
 * @method static Http\Response setEtag(string $etag = null, bool $weak = false)
 * @method static Http\Response setCache(array $options)
 * @method static Http\Response setEmitter(EmitterInterface | \Closure $emitter)
 */
class Response extends Facade
{
}