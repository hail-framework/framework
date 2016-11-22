<?php
namespace Hail\Cache\Driver;

use Hail\Cache\Driver;

/**
 * Array cache driver.
 *
 * @link   www.doctrine-project.org
 * @since  2.0
 * @author Benjamin Eberlei <kontakt@beberlei.de>
 * @author Guilherme Blanco <guilhermeblanco@hotmail.com>
 * @author Jonathan Wage <jonwage@gmail.com>
 * @author Roman Borschel <roman@code-factory.org>
 * @author David Abdemoulaie <dave@hobodave.com>
 * @author Hao Feng <flyinghail@msn.com>
 */
class ArrayData extends Driver
{
	/**
	 * @var array $data
	 */
	private $data = array();

	public function __construct($params)
	{
		parent::__construct($params);
	}

	/**
	 * {@inheritdoc}
	 */
	protected function doFetch($id)
	{
		return $this->doContains($id) ? $this->data[$id] : false;
	}

	/**
	 * {@inheritdoc}
	 */
	protected function doContains($id)
	{
		// isset() is required for performance optimizations, to avoid unnecessary function calls to array_key_exists.
		return isset($this->data[$id]) || array_key_exists($id, $this->data);
	}

	/**
	 * {@inheritdoc}
	 */
	protected function doSave($id, $data, $lifeTime = 0)
	{
		$this->data[$id] = $data;
		return true;
	}

	/**
	 * {@inheritdoc}
	 */
	protected function doDelete($id)
	{
		unset($this->data[$id]);
		return true;
	}

	/**
	 * {@inheritdoc}
	 */
	protected function doFlush()
	{
		$this->data = array();
		return true;
	}

	/**
	 * {@inheritdoc}
	 */
	protected function doGetStats()
	{
		return null;
	}
}