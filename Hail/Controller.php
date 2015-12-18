<?php
/**
 * Created by IntelliJ IDEA.
 * User: Hao
 * Date: 2015/12/16 0016
 * Time: 15:07
 */

namespace Hail;


abstract class Controller
{
	use DITrait;

	protected $dispatcher;

	public function __construct($dispatcher)
	{
		$this->dispatcher = $dispatcher;
	}

	abstract public function IndexAction();
}