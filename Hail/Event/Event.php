<?php

namespace Hail\Event;

use Psr\EventManager\EventInterface;

/**
 * Event
 *
 * Basic implementation of EventInterface
 *
 * ```php
 * // create event
 * $evt = new Event(
 *     'login.attempt',          // event name
 *     ['username' => 'phossa'], // event parameters
 *     $this                     // event target
 * );
 *
 * // stop event
 * $evt->stopPropagation();
 * ```
 *
 */
class Event implements EventInterface, \ArrayAccess, \IteratorAggregate
{
	/**
	 * event name
	 *
	 * @var    string
	 * @access protected
	 */
	protected $name;

	/**
	 * event target/context
	 *
	 * an object OR static class name (string)
	 *
	 * @var    object|string|null
	 * @access protected
	 */
	protected $target;

	/**
	 * event parameters
	 *
	 * @var    array
	 * @access protected
	 */
	protected $parameters;

	/**
	 * stop propagation
	 *
	 * @var    bool
	 * @access protected
	 */
	protected $stopped = false;

	/**
	 * Constructor
	 *
	 * @param  string             $eventName event name
	 * @param  array              $params    (optional) event parameters
	 * @param  string|object|null $target    event context, object or classname
	 *
	 * @access public
	 * @api
	 */
	public function __construct(
		string $eventName,
		array $params = [],
		$target = null
	)
	{
		$this->setName($eventName);
		$this->setTarget($target);
		$this->setParams($params);
	}

	/**
	 * {@inheritDoc}
	 */
	public function getName()
	{
		return $this->name;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getTarget()
	{
		return $this->target;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getParams()
	{
		return $this->parameters;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getParam($name)
	{
		$name = (string) $name;

		return $this->parameters[$name] ?? null;
	}

	/**
	 * {@inheritDoc}
	 */
	public function setName($name)
	{
		$this->name = (string) $name;
	}

	/**
	 * {@inheritDoc}
	 */
	public function setTarget($target)
	{
		$this->target = $target;
	}

	/**
	 * {@inheritDoc}
	 */
	public function setParams(array $params)
	{
		$this->parameters = $params;
	}

	/**
	 * {@inheritDoc}
	 */
	public function stopPropagation($flag)
	{
		$this->stopped = (bool) $flag;
	}

	/**
	 * {@inheritDoc}
	 */
	public function isPropagationStopped()
	{
		return $this->stopped;
	}

	/** extend */

	/**
	 * Add parameter to event.
	 *
	 * @param string $key   Parameter name
	 * @param mixed  $value Value
	 *
	 * @return mixed
	 */
	public function setParam($key, $value)
	{
		return $this->parameters[$key] = $value;
	}

	/**
	 * Has parameter.
	 *
	 * @param string $key Key of parameters array
	 *
	 * @return bool
	 */
	public function hasParam($key)
	{
		return array_key_exists($key, $this->parameters);
	}

	/**
	 * ArrayAccess for argument getter.
	 *
	 * @param string $key Array key
	 *
	 * @return mixed
	 */
	public function offsetGet($key)
	{
		return $this->getParam($key);
	}

	/**
	 * ArrayAccess for argument setter.
	 *
	 * @param string $key   Array key to set
	 * @param mixed  $value Value
	 */
	public function offsetSet($key, $value)
	{
		$this->setParam($key, $value);
	}

	/**
	 * ArrayAccess for unset argument.
	 *
	 * @param string $key Array key
	 */
	public function offsetUnset($key)
	{
		if ($this->hasParam($key)) {
			unset($this->parameters[$key]);
		}
	}

	/**
	 * ArrayAccess has argument.
	 *
	 * @param string $key Array key
	 *
	 * @return bool
	 */
	public function offsetExists($key)
	{
		return $this->hasParam($key);
	}

	/**
	 * IteratorAggregate for iterating over the object like an array.
	 *
	 * @return \ArrayIterator
	 */
	public function getIterator()
	{
		return new \ArrayIterator($this->parameters);
	}
}