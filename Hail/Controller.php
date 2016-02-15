<?php
/**
 * Created by IntelliJ IDEA.
 * User: FlyingHail
 * Date: 2015/12/16 0016
 * Time: 15:07
 */

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