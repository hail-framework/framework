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

use Hail\Event\Psr\EventInterface;
use Hail\Event\Psr\EventManagerInterface;

/**
 * Class EventManager
 *
 * @package Hail\Event
 * @author  Hao Feng <flyinghail@msn.com>
 */
class EventManager implements EventManagerInterface
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
	 * @var callable[][]
	 */
	private $sorted = [];

    /**
	 * @inheritdoc
	 * @throws \InvalidArgumentException
	 */
	public function trigger($event, $target = null, $argv = [])
	{
		if ($isName = \is_string($event)) {
			$eventName = $event;
		} elseif ($event instanceof EventInterface) {
			$eventName = $event->getName();
			if ($argv !== []) {
				$event->setParams($argv);
			}

			if ($target !== null) {
				$event->setTarget($target);
			}
		} else {
			throw new \InvalidArgumentException('Event type must be EventInterface or string.');
		}

		if ($listeners = $this->getListeners($eventName)) {
			if ($isName) {
				$event = new Event($eventName, $argv, $target);
			}

			foreach ($listeners as $listener) {
				if ($event->isPropagationStopped()) {
					break;
				}

				$listener($event, $this);
			}
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

		return \array_filter($this->sorted);
	}

	/**
	 * @inheritdoc
	 */
	public function attach($event, $callback, $priority = 0)
	{
		$this->listeners[$event][$priority][] = $callback;
		unset($this->sorted[$event]);

		return true;
	}

	/**
	 * @inheritdoc
	 */
	public function detach($event, $callback)
	{
		if (isset($this->listeners[$event])) {
			foreach ($this->listeners[$event] as $priority => $listeners) {
				if (false !== ($key = \array_search($callback, $listeners, true))) {
					unset($this->listeners[$event][$priority][$key], $this->sorted[$event]);
				}

				if ($this->listeners[$event][$priority] === []) {
					unset($this->listeners[$event][$priority]);
				}
			}

			if ($this->listeners[$event] === []) {
				unset($this->listeners[$event]);
			}
		}

		return true;
	}

	/**
	 * @inheritdoc
	 */
	public function clearListeners($event)
	{
		unset($this->listeners[$event], $this->sorted[$event]);
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
				if (false !== \in_array($listener, $listeners, true)) {
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
			if (\is_string($params)) {
				$this->attach($eventName, [$subscriber, $params]);
			} elseif (\is_array($params)) {
				if (\is_string($params[0])) {
					$this->attach($eventName, [$subscriber, $params[0]], $params[1] ?? 0);
				} else {
					foreach ($params as $listener) {
						$this->attach($eventName, [$subscriber, $listener[0]], $listener[1] ?? 0);
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
			if (\is_array($params) && \is_array($params[0])) {
				foreach ($params as $listener) {
					$this->detach($eventName, [$subscriber, $listener[0]]);
				}
			} else {
				$this->detach($eventName, [$subscriber, \is_string($params) ? $params : $params[0]]);
			}
		}

		return $this;
	}

	/**
	 * Sorts the internal list of listeners for the given event by priority.
	 *
	 * @param string $eventName The name of the event
	 */
	private function sortListeners(string $eventName)
	{
        $listeners = &$this->listeners[$eventName];

		\krsort($listeners);
		$this->sorted[$eventName] = \array_merge(...$listeners);
	}
}
