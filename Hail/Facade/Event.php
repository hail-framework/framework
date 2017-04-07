<?php
namespace Hail\Facade;

use Psr\EventManager\EventInterface;
use Hail\Event\{
	EventManager,
	EventSubscriberInterface
};

/**
 * Class Event
 *
 * @package Hail\Facade
 *
 * @method static bool attach(string $event, callable $callback, $priority = 0)
 * @method static bool detach(string $event, callable $callback)
 * @method static void clearListeners(string $event)
 * @method static EventManager addSubscriber(EventSubscriberInterface $subscriber)
 * @method static EventManager removeSubscriber(EventSubscriberInterface $subscriber)
 * @method static bool hasListeners(string $event)
 * @method static int getListenerPriority(string $event, callable $listener)
 * @method static callable[] getListeners(string $eventName = null)
 * @method static EventInterface trigger(string|EventInterface $event, object|string $target = null, array|iterable $argv = [])
 */
class Event extends Facade
{
}