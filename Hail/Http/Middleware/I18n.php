<?php

namespace Hail\Http\Middleware;

use Hail\Container\Container;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\ServerMiddleware\MiddlewareInterface;
use Psr\Http\ServerMiddleware\DelegateInterface;

class I18n implements MiddlewareInterface
{
    protected $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * Process a server request and return a response.
     *
     * @param ServerRequestInterface $request
     * @param DelegateInterface      $delegate
     *
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, DelegateInterface $delegate)
    {
        $config = $this->container->get('config');

        $locale = $config->get('app.i18n.locale');

        if (is_array($locale)) {
            $found = null;
            foreach ($locale as $k => $v) {
                switch ($k) {
                    case 'query':
                        $found = $request->getQueryParams()[$v] ?? null;
                        break;
                    case 'cookie':
                        $found = $request->getCookieParams()[$v] ?? null;
                        break;
                    case 'default':
                        $found = $v;
                        break;
                }

                if ($found) {
                    break;
                }
            }

            $locale = $found;
        }

        if ($locale) {
            $locale = str_replace('-', '_', $locale);

            $alias = $config->get('app.i18n.alias');
            if (!empty($alias)) {
                $locale = $alias[explode('_', $locale)[0]] ?? $locale;
            }

            $this->container->get('i18n')->init(
                BASE_PATH . 'lang',
                $config->get('app.i18n.domain'),
                $locale
            );
        }

        return $delegate->process($request);
    }
}