<?php

namespace Hail;

use Psr\Http\Message\ServerRequestInterface;

/**
 * Class Controller
 *
 * @package Hail
 */
abstract class Controller
{
	use DITrait;

	/**
	 * @var ServerRequestInterface
	 */
	protected $request;

	public function __construct(ServerRequestInterface $request)
	{
		$this->request = $request;
	}
}