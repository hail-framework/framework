<?php

namespace Hail\Http\Middleware;


use Hail\Http\Factory;
use Hail\Http\Middleware\Util\NegotiatorTrait;
use Psr\Http\{
    Server\MiddlewareInterface,
    Server\RequestHandlerInterface,
    Message\ServerRequestInterface,
    Message\ResponseInterface
};

class ContentLanguage implements MiddlewareInterface
{
    use NegotiatorTrait;

    /**
     * @var array Allowed languages
     */
    private $languages;

    /**
     * @var bool Use the path to detect the language
     */
    private $usePath = false;

    /**
     * @var bool Returns a redirect response or not
     */
    private $redirect = false;

    /**
     * Define de available languages.
     *
     * @param array $languages
     */
    public function __construct(array $languages)
    {
        $this->languages = $languages;
    }

    /**
     * Use the base path to detect the current language.
     *
     * @param bool $usePath
     *
     * @return self
     */
    public function usePath($usePath = true)
    {
        $this->usePath = $usePath;

        return $this;
    }

    /**
     * Whether returns a 302 response to the new path.
     * Note: This only works if usePath is true.
     *
     * @param bool $redirect
     *
     * @return self
     */
    public function redirect($redirect = true)
    {
        $this->redirect = (bool) $redirect;

        return $this;
    }

    /**
     * Process a server request and return a response.
     *
     * @param ServerRequestInterface $request
     * @param RequestHandlerInterface      $handler
     *
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $uri = $request->getUri();
        $language = $this->detectFromPath($uri->getPath());

        if ($language === null) {
            $accept = $request->getHeaderLine('Accept-Language');
            $language = $this->getBest($accept, $this->languages);

            if (empty($language)) {
                $language = $this->languages[0] ?? '';
            }

            if ($this->redirect && $this->usePath) {
                $location = $uri->withPath(str_replace('//', '/', $language.'/'.$uri->getPath()));

                return Factory::response(302, null, [
                    'Location' => (string) $location
                ]);
            }
        }

        $response = $handler->handle($request->withHeader('Accept-Language', $language));

        if (!$response->hasHeader('Content-Language')) {
            return $response->withHeader('Content-Language', $language);
        }

        return $response;
    }

    /**
     * Returns the format using the file extension.
     *
     * @param string $path
     *
     * @return null|string
     */
    private function detectFromPath($path)
    {
        if (!$this->usePath) {
            return null;
        }

        $first = \explode('/', \ltrim($path, '/'), 2)[0];
        $first = \strtolower($first);

        if (!empty($first) && \in_array($first, $this->languages, true)) {
            return $first;
        }

        return null;
    }

    protected function pareseAccept($accept): array
    {
        $accept = self::normalPareseAccept($accept);

        $parts = \explode('-', $accept['type']);

        switch (\count($parts)) {
            case 1:
                $accept['basePart'] = $parts[0];
                break;

            case 2:
                $accept['basePart'] = $parts[0];
                $accept['subPart'] = $parts[1];
                break;

            case 3:
                $accept['basePart'] = $parts[0];
                $accept['subPart'] = $parts[2];
                break;

            default:
                // TODO: this part is never reached...
                throw new \RuntimeException('Accept-Languange header parse failed: ' . $accept['type']);
        }

        return $accept;
    }

    /**
     * {@inheritdoc}
     */
    protected function match(array $accept, array $priority, $index)
    {
        $ab = $accept['basePart'];
        $pb = $priority['basePart'];

        $as = $accept['subPart'];
        $ps = $priority['subPart'];

        $baseEqual = !\strcasecmp($ab, $pb);
        $subEqual = !\strcasecmp($as, $ps);

        if (($ab === '*' || $baseEqual) && ($as === null || $subEqual)) {
            $score = 10 * $baseEqual + $subEqual;

            return [
                'quality' => $accept['quality'] * $priority['quality'],
                'score' => $score,
                'index' => $index,
            ];
        }

        return null;
    }
}