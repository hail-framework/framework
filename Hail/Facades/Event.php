<?php
namespace Hail\Facades;

use Hail\Event\{
	Emitter,
	Listener
};

/**
 * Class Event
 *
 * @package Hail\Facades
 *
 * @method static Emitter on(string $event, Listener|callable $listener, $priority = Emitter::P_NORMAL)
 * @method static Emitter once(string $event, callable $listener, $priority = Emitter::P_NORMAL)
 * @method static Emitter removeListener(string $event, callable $listener)
 * @method static Emitter removeAllListeners(string $event)
 * @method static bool hasListeners(string $event)
 * @method static Listener[] getListeners(string $event)
 * @method static Listener[] getSortedListeners(string $event)
 * @method static void emit(string $event, array ...$arguments)
 * @method static void emitBatch(array $events)
 */
class Event extends Facade
{
	protected static function instance()
	{
		return new Emitter();
	}
}