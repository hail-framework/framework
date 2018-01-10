<?php

namespace Hail\Session\Handler;

/**
 * Class BaseHandler
 *
 * @package Hail\Session
 */
abstract class BaseHandler implements \SessionHandlerInterface
{
	protected $settings = [];

	public function __construct(array $settings = [])
	{
		if ($settings !== []) {
			$this->settings = $settings;
		}
	}

	protected function key($id)
	{
		if (!empty($this->settings['prefix'])) {
			return $this->settings['prefix'] . '_' . $id;
		}

		return $id;
	}
}