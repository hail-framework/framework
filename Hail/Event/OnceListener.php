<?php

namespace Hail\Event;

/**
 * Class OnceListener
 * @package Hail\Event
 */
class OnceListener extends Listener
{
	/**
	 * @var string
	 */
	protected $event;

	/**
	 * @var Emitter
	 */
	protected $emitter;

	/**
	 * Create a new callback listener instance.
	 *
	 * @param string $event
	 * @param Emitter $emitter
	 * @param callable $callback
	 */
	public function __construct($event, $emitter, callable $callback)
	{
		$this->event = $event;
		$this->emitter = $emitter;
		parent::__construct($callback);
	}

	/**
	 * Handle an event.
	 *
	 * @param array $args
	 */
	public function handle($args)
	{
		$this->emitter->removeListener($this->event, $this->callback);
		parent::handle($args);
	}
}
