<?php
/**
 * Created by IntelliJ IDEA.
 * User: FlyingHail
 * Date: 2016/2/15 0015
 * Time: 18:06
 */

namespace Hail\Session;


use Hail\DITrait;

/**
 * Class BaseHandler
 *
 * @package Hail\Session
 */
abstract class BaseHandler implements \SessionHandlerInterface
{
	use DITrait;

	protected $settings = [];

	public function __construct(array $settings = [])
	{
		if ($settings !== []) {
			$this->settings = array_merge($this->settings, $settings);
		}
	}
}