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
	 * @param \Closure $callback
	 */
	public function __construct(\Closure $callback)
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
		switch (count($args)) {
			case 1:
				($this->callback)($args[0]);
				return;
			case 2:
				($this->callback)($args[0], $args[1]);
				return;
			case 3:
				($this->callback)($args[0], $args[1], $args[2]);
				return;
			case 4:
				($this->callback)($args[0], $args[1], $args[2], $args[3]);
				return;
			default:
				call_user_func_array($this->callback, $args);
				return;
		}
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
