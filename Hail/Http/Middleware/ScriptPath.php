<?php

namespace Hail\Http\Middleware;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\ServerMiddleware\DelegateInterface;

class ScriptPath extends BasePath
{
	public function __construct()
	{
	}

	public function process(ServerRequestInterface $request, DelegateInterface $delegate)
	{
		$path = $request->getUri()->getPath();

		$lpath = strtolower($path);
		$script = isset($_SERVER['SCRIPT_NAME']) ? strtolower($_SERVER['SCRIPT_NAME']) : '';
		if ($lpath !== $script) {
			$max = min(strlen($lpath), strlen($script));
			for ($i = 0; $i < $max && $lpath[$i] === $script[$i]; $i++) {
				;
			}
			$path = $i ? substr($path, 0, strrpos($path, '/', $i - strlen($path) - 1) + 1) : '/';
		}

		$this->setBasePath($path);

		return parent::process($request, $delegate);

	}
}