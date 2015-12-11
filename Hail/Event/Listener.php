<?php

namespace Hail\Event;

class Listener
{
	/**
	 * The callback.
	 *
	 * @var callable
	 */
	protected $callback;

	/**
	 * Create a new callback listener instance.
	 *
	 * @param callable $callback
	 */
	public function __construct(callable $callback)
	{
		$this->callback = $callback;
	}

	/**
	 * Get the callback.
	 *
	 * @return callable
	 */
	public function getCallback()
	{
		return $this->callback;
	}


	/**
	 * Handle an event.
	 *
	 * @param array $args
	 */
	public function handle($args)
	{
		call_user_func_array($this->callback, $args);
	}

	/**
	 * Check whether the listener is the given parameter.
	 *
	 * @param callable $listener
	 *
	 * @return bool
	 */
	public function isEqual(callable $listener)
	{
		if ($listener instanceof Listener) {
			$listener = $listener->getCallback();
		}

		return $this->callback === $listener;
	}
}
