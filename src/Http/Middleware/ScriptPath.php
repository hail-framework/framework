<?php

namespace Hail\Http\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class ScriptPath extends BasePath
{
	public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
	{
		$path = $request->getUri()->getPath();

		$script = $request->getServerParams()['SCRIPT_NAME'] ?? '';
		if ($path !== $script) {
			$parts = \explode('/', $path);
			$script = \explode('/', $script);

			$path = '';
			foreach($parts as $k => $v) {
				if ($script[$k] === $v) {
					$path .= $v . '/';
				} else {
					break;
				}
			}
		}

		$this->setBasePath($path);

		return parent::process($request, $handler);

	}
}