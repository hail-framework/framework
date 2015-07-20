<?php

namespace Hail\Event;

class Emitter
{
	/**
	 * High priority.
	 *
	 * @const int
	 */
	const P_HIGH = 100;

	/**
	 * Normal priority.
	 *
	 * @const int
	 */
	const P_NORMAL = 0;

	/**
	 * Low priority.
	 *
	 * @const int
	 */
	const P_LOW = -100;

	/**
	 * The registered listeners.
	 *
	 * @var array
	 */
	protected $listeners = [];

	/**
	 * The sorted listeners
	 *
	 * Listeners will get sorted and stored for re-use.
	 *
	 * @var Listener[]
	 */
	protected $sortedListeners = [];

	/**
	 * Add a listener for an event.
	 *
	 * The first parameter should be the event name, and the second should be
	 * the event listener. In this case, the priority emitter also accepts
	 * an optional third parameter specifying the priority as an integer. You
	 * may use one of our predefined constants here if you want.
	 *
	 * @param string                     $event
	 * @param Listener|callable $listener
	 * @param int                        $priority
	 *
	 * @return $this
	 */
	public function on($event, $listener, $priority = self::P_NORMAL)
	{
		$this->listeners[$event][$priority][] = new Listener($listener);
		$this->clearSortedListeners($event);

		return $this;
	}

	/**
	 * Add a one time listener for an event.
	 *
	 * The first parameter should be the event name, and the second should be
	 * the event listener.
	 *
	 * @param string   $event
	 * @param Listener|callable $listener
	 * @param int      $priority
	 *
	 * @return $this
	 */
	public function once($event, $listener, $priority = self::P_NORMAL)
	{
		$listener = new OnceListener($event, $this, $listener);
		return $this->on($event, $listener, $priority);
	}

	/**
	 * Remove a specific listener for an event.
	 *
	 * The first parameter should be the event name, and the second should be
	 * the event listener.
	 *
	 * @param string                     $event
	 * @param callable $listener
	 *
	 * @return $this
	 */
	public function removeListener($event, callable $listener)
	{
		$this->clearSortedListeners($event);
		$listeners = $this->hasListeners($event)
			? $this->listeners[$event]
			: [];

		$filter = function ($registered) use ($listener) {
			return ! $registered->isEqual($listener);
		};

		foreach ($listeners as $priority => $collection) {
			$listeners[$priority] = array_filter($collection, $filter);
		}

		$this->listeners[$event] = $listeners;

		return $this;
	}

	/**
	 * Remove all listeners for an event.
	 *
	 * The first parameter should be the event name. All event listeners will
	 * be removed.
	 *
	 * @param string $event
	 *
	 * @return $this
	 */
	public function removeAllListeners($event)
	{
		$this->clearSortedListeners($event);

		if ($this->hasListeners($event)) {
			unset($this->listeners[$event]);
		}

		return $this;
	}

	/**
	 * Check whether an event has listeners.
	 *
	 * The first parameter should be the event name. We'll return true if the
	 * event has one or more registered even listeners, and false otherwise.
	 *
	 * @param string $event
	 *
	 * @return bool
	 */
	public function hasListeners($event)
	{
		return isset($this->listeners[$event]) && count($this->listeners[$event]) > 0;
	}

	/**
	 * Get all the listeners for an event.
	 *
	 * The first parameter should be the event name. We'll return an array of
	 * all the registered even listeners, or an empty array if there are none.
	 *
	 * @param string $event
	 *
	 * @return Listener[]
	 */
	public function getListeners($event)
	{
		if (array_key_exists($event, $this->sortedListeners)) {
			return $this->sortedListeners[$event];
		}

		return $this->sortedListeners[$event] = $this->getSortedListeners($event);
	}

	/**
	 * Get the listeners sorted by priority for a given event.
	 *
	 * @param string $event
	 *
	 * @return Listener[]
	 */
	protected function getSortedListeners($event)
	{
		if (! $this->hasListeners($event)) {
			return [];
		}

		$listeners = $this->listeners[$event];
		krsort($listeners);

		return call_user_func_array('array_merge', $listeners);
	}

	/**
	 * Emit an event.
	 *
	 * @param string $event
	 * @param array $arguments
	 */
	public function emit($event, ...$arguments)
	{
		$this->invokeListeners($event, $arguments);
		$this->invokeListeners('*', $arguments);
	}

	/**
	 * Emit a batch of events.
	 *
	 * @param array $events
	 */
	public function emitBatch(array $events)
	{
		foreach ($events as $event) {
			$this->emit($event);
		}
	}

	/**
	 * Invoke the listeners for an event.
	 *
	 * @param string        $name
	 * @param array         $arguments
	 *
	 * @return void
	 */
	protected function invokeListeners($name, array $arguments)
	{
		$listeners = $this->getListeners($name);

		foreach ($listeners as $listener) {
			$listener->handle($arguments);
		}
	}

	/**
	 * Clear the sorted listeners for an event
	 *
	 * @param $event
	 */
	protected function clearSortedListeners($event)
	{
		unset($this->sortedListeners[$event]);
	}
}
