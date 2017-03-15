<?php
/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Hail\Event;

/**
 * The EventDispatcherInterface is the central point of Symfony's event listener system.
 *
 * Listeners are registered on the manager and events are dispatched through the
 * manager.
 *
 * @author Guilherme Blanco <guilhermeblanco@hotmail.com>
 * @author Jonathan Wage <jonwage@gmail.com>
 * @author Roman Borschel <roman@code-factory.org>
 * @author Bernhard Schussek <bschussek@gmail.com>
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Jordi Boggiano <j.boggiano@seld.be>
 * @author Jordan Alliot <jordan.alliot@gmail.com>
 * @author Hao Feng <flyinghail@msn.com>
 */
class EventDispatcher
{
	/**
	 * The registered listeners.
	 *
	 * @var array [:eventName => [:priority => callable[]]]
	 */
	private $listeners = [];

	/**
	 * The sorted listeners
	 * Listeners will get sorted and stored for re-use.
	 *
	 * @var [callable[]]
	 */
	private $sorted = [];

	/**
	 * Dispatches an event to all registered listeners.
	 *
	 * @param string $eventName The name of the event to dispatch. The name of
	 *                          the event is the name of the method that is
	 *                          invoked on listeners.
	 * @param Event  $event     The event to pass to the event handlers/listeners
	 *                          If not supplied, an empty Event instance is created.
	 *
	 * @return Event
	 */
	public function dispatch(string $eventName, Event $event = null)
	{
		if ($listeners = $this->getListeners($eventName)) {
			$this->doDispatch($listeners, $eventName, $event ?? new Event());
		}

		return $event;
	}

	/**
	 * Gets the listeners of a specific event or all listeners sorted by descending priority.
	 *
	 * @param string $eventName The name of the event
	 *
	 * @return array The event listeners for the specified event, or all event listeners by event name
	 */
	public function getListeners(string $eventName = null)
	{
		if (null !== $eventName) {
			if (!isset($this->listeners[$eventName])) {
				return [];
			}

			if (!isset($this->sorted[$eventName])) {
				$this->sortListeners($eventName);
			}

			return $this->sorted[$eventName];
		}

		foreach ($this->listeners as $name => $listeners) {
			if (!isset($this->sorted[$name])) {
				$this->sortListeners($name);
			}
		}

		return array_filter($this->sorted);
	}

	/**
	 * Gets the listener priority for a specific event.
	 *
	 * Returns null if the event or the listener does not exist.
	 *
	 * @param string   $eventName The name of the event
	 * @param callable $listener  The listener
	 *
	 * @return int|null The event listener priority
	 */
	public function getListenerPriority(string $eventName, callable $listener)
	{
		if (isset($this->listeners[$eventName])) {
			foreach ($this->listeners[$eventName] as $priority => $listeners) {
				if (false !== in_array($listener, $listeners, true)) {
					return $priority;
				}
			}
		}

		return null;
	}

	/**
	 * Checks whether an event has any registered listeners.
	 *
	 * @param string $eventName The name of the event
	 *
	 * @return bool true if the specified event has any listeners, false otherwise
	 */
	public function hasListeners(string $eventName = null)
	{
		return isset($this->listeners[$eventName]);
	}

	public function listen(string $eventName, callable $listener, int $priority = 0)
	{
		$this->addListener($eventName, $listener, $priority);
	}

	/**
	 * Adds an event listener that listens on the specified events.
	 *
	 * @param string   $eventName          The event to listen on
	 * @param callable $listener           The listener
	 * @param int      $priority           The higher this value, the earlier an event
	 *                                     listener will be triggered in the chain (defaults to 0)
	 *
	 * @return $this
	 */
	public function addListener(string $eventName, callable $listener, int $priority = 0)
	{
		$this->listeners[$eventName][$priority][] = $listener;
		unset($this->sorted[$eventName]);

		return $this;
	}

	/**
	 * Removes an event listener from the specified events.
	 *
	 * @param string   $eventName The event to remove a listener from
	 * @param callable $listener  The listener to remove
	 *
	 * @return $this
	 */
	public function removeListener(string $eventName, callable $listener)
	{
		if (isset($this->listeners[$eventName])) {
			foreach ($this->listeners[$eventName] as $priority => $listeners) {
				if (false !== ($key = array_search($listener, $listeners, true))) {
					unset($this->listeners[$eventName][$priority][$key], $this->sorted[$eventName]);
				}

				if ($this->listeners[$eventName][$priority] === []) {
					unset($this->listeners[$eventName][$priority]);
				}
			}

			if ($this->listeners[$eventName] === []) {
				unset($this->listeners[$eventName]);
			}
		}

		return $this;
	}

	/**
	 * Remove all listeners for an event.
	 *
	 * The first parameter should be the event name. All event listeners will
	 * be removed.
	 *
	 * @param string $eventName
	 *
	 * @return $this
	 */
	public function removeAllListeners(string $eventName)
	{
		unset($this->listeners[$eventName], $this->sorted[$eventName]);

		return $this;
	}

	/**
	 * Adds an event subscriber.
	 *
	 * The subscriber is asked for all the events he is
	 * interested in and added as a listener for these events.
	 *
	 * @param EventSubscriberInterface $subscriber The subscriber
	 *
	 * @return $this
	 */
	public function addSubscriber(EventSubscriberInterface $subscriber)
	{
		foreach ($subscriber->getSubscribedEvents() as $eventName => $params) {
			if (is_string($params)) {
				$this->addListener($eventName, [$subscriber, $params]);
			} elseif (is_array($params)) {
				if (is_string($params[0])) {
					$this->addListener($eventName, [$subscriber, $params[0]], $params[1] ?? 0);
				} else {
					foreach ($params as $listener) {
						$this->addListener($eventName, [$subscriber, $listener[0]], $listener[1] ?? 0);
					}
				}
			}
		}

		return $this;
	}

	/**
	 * Removes an event subscriber.
	 *
	 * @param EventSubscriberInterface $subscriber The subscriber
	 *
	 * @return $this
	 */
	public function removeSubscriber(EventSubscriberInterface $subscriber)
	{
		foreach ($subscriber->getSubscribedEvents() as $eventName => $params) {
			if (is_array($params) && is_array($params[0])) {
				foreach ($params as $listener) {
					$this->removeListener($eventName, [$subscriber, $listener[0]]);
				}
			} else {
				$this->removeListener($eventName, [$subscriber, is_string($params) ? $params : $params[0]]);
			}
		}

		return $this;
	}

	/**
	 * Triggers the listeners of an event.
	 *
	 * This method can be overridden to add functionality that is executed
	 * for each listener.
	 *
	 * @param callable[] $listeners The event listeners
	 * @param string     $eventName The name of the event to dispatch
	 * @param Event      $event     The event object to pass to the event handlers/listeners
	 */
	protected function doDispatch(array $listeners, string $eventName, Event $event)
	{
		foreach ($listeners as $listener) {
			if ($event->isPropagationStopped()) {
				break;
			}

			$listener($event, $eventName, $this);
		}
	}

	/**
	 * Sorts the internal list of listeners for the given event by priority.
	 *
	 * @param string $eventName The name of the event
	 */
	private function sortListeners(string $eventName)
	{
		krsort($this->listeners[$eventName]);
		$this->sorted[$eventName] = call_user_func_array('array_merge', $this->listeners[$eventName]);
	}
}
