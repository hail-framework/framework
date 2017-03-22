<?php
namespace Hail\Facades;

use Hail\Event\{
	EventDispatcher,
	Event as EventArgs,
	EventSubscriberInterface
};

/**
 * Class Event
 *
 * @package Hail\Facades
 *
 * @method static EventDispatcher listen(string $eventName, callable $listener, $priority = 0)
 * @method static EventDispatcher addListener(string $eventName, callable $listener, $priority = 0)
 * @method static EventDispatcher removeListener(string $eventName, callable $listener)
 * @method static EventDispatcher removeAllListeners(string $eventName)
 * @method static EventDispatcher addSubscriber(EventSubscriberInterface $subscriber)
 * @method static EventDispatcher removeSubscriber(EventSubscriberInterface $subscriber)
 * @method static bool hasListeners(string $event)
 * @method static int getListenerPriority(string $eventName, callable $listener)
 * @method static callable[] getListeners(string $eventName = null)
 * @method static EventArgs dispatch(string $eventName, EventArgs $event = null)
 */
class Event extends Facade
{
	protected static function instance()
	{
		return new EventDispatcher();
	}
}