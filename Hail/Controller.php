<?php

namespace Hail;

/**
 * Class Controller
 *
 * @package Hail
 */
abstract class Controller
{
	use DITrait;

	/**
	 * @var Dispatcher
	 */
	protected $dispatcher;

	public function __construct($dispatcher)
	{
		$this->dispatcher = $dispatcher;
	}

	abstract public function indexAction();
}