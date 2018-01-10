<?php

namespace Hail\Swoole\Http;

use Hail\Application;
use Hail\Framework;
use Hail\Http\Factory;
use Hail\Http\Helpers;
use Hail\Http\Message\ServerRequest;
use Hail\Http\Message\Uri;
use Hail\Promise\Util as Promise;
use Psr\Http\Message\ServerRequestInterface;
use Swoole\Http;

class Server
{
    /**
     * @var Application
     */
    protected $app;

    /**
     * @var Http\Response
     */
    protected static $response;

    public function listen(string $ip, int $port): void
    {
        \defined('HAIL_SERVER_ENV') || \define('HAIL_SERVER_ENV' , 'SWOOLE_HTTP');

        $server = new Http\Server($ip, $port);

        $server->set($this->app->config('swoole.http'));

        $server->on('Start', [$this, 'onStart']);
        $server->on('WorkerStart', [$this, 'onWorkerStart']);
        $server->on('request', [$this, 'onReqeust']);

        $server->start();
    }

    public function onStart()
    {
        \cli_set_process_title('hail-swoole-http: master');
    }

    public function onWorkerStart()
    {
        // which files can not reload
        //\var_dump(\get_included_files());

        if (\function_exists('\opcache_reset')) {
            \opcache_reset();
        }

        \cli_set_process_title('hail-swoole-http: worker');

        $application = new Application(
            Framework::getContainer()
        );

        $application->emitter(
            Emitter::class,
            EmitterFile::class
        );

        $this->app = $application;
    }

    public function onReqeust($request, $response)
    {
        $this->app->createServer(
            $this->creatServerRequest($request)
        );

        static::$response = $response;

        try {
            $this->app->listen();
        } catch (\Throwable $e) {
            // do nothing
        } finally {
            Promise::shutdown();
            $this->app->reset();
            static::$response = null;

            \gc_collect_cycles();
        }
    }

    public static function getResponse(): Http\Response
    {
        return static::$response;
    }

    public function creatServerRequest(Http\Request $request): ServerRequestInterface
    {
        $host = '::1';
        foreach (['host', 'server_addr'] as $name) {
            if (!empty($request->header[$name])) {
                $host = \parse_url($request->header[$name], PHP_URL_HOST) ?: $request->header[$name];
            }
        }

        $server = [];
        foreach ($request->server as $k => $v) {
            $server[\strtoupper($k)] = $v;
        }

        $server = \array_merge($server, [
            'GATEWAY_INTERFACE' => 'swoole/' . SWOOLE_VERSION,

            // Server
            'SERVER_PROTOCOL' => $request->header['server_protocol'] ?? $server['SERVER_PROTOCOL'],
            'REQUEST_SCHEMA' => $request->header['request_scheme'] ?? \explode('/', $server['SERVER_PROTOCOL'])[0],
            'SERVER_NAME' => $request->header['server_name'] ?? $host,
            'SERVER_ADDR' => $host,
            'SERVER_PORT' => $request->header['server_port'] ?? $server['SERVER_PORT'],
            'REMOTE_ADDR' => $host,
            'REMOTE_PORT' => $request->header['remote_port'] ?? $server['REMOTE_PORT'],
            'QUERY_STRING' => $request->server['query_string'] ?? '',

            // Headers
            'HTTP_HOST' => $host,
            'HTTP_USER_AGENT' => $request->header['user-agent'] ?? '',
            'HTTP_ACCEPT' => $request->header['accept'] ?? '*/*',
            'HTTP_ACCEPT_LANGUAGE' => $request->header['accept-language'] ?? '',
            'HTTP_ACCEPT_ENCODING' => $request->header['accept-encoding'] ?? '',
            'HTTP_CONNECTION' => $request->header['connection'] ?? '',
            'HTTP_CACHE_CONTROL' => $request->header['cache-control'] ?? '',
        ]);

        $files = Helpers::normalizeFiles($request->files ?? []);

        $method = Helpers::getMethod($server);

        $headers = [];
        foreach ($request->header as $k => $v) {
            $headers[Helpers::normalizeHeaderName($k)] = $v;
        }

        if (!isset($server['HTTPS'])) {
            $server['HTTPS'] = 'off';
        }

        if (
            $server['HTTPS'] === 'off' &&
            isset($headers['X-Forwarded-Proto']) &&
            $headers['X-Forwarded-Proto'] === 'https'
        ) {
            $server['HTTPS'] = 'on';
        }

        $uri = Uri::fromArray($server);

        $protocol = Helpers::getProtocol($server);
        $stream = Factory::stream($request->rawcontent() ?? '');

        return new ServerRequest(
            $method, $uri, $headers, $stream,
            $protocol, $server, $request->cookie ?? [],
            $request->get ?? [], $request->post ?? [], $files
        );
    }
}