<?php
namespace Hail\Http;

use Hail\Facade\Arrays;
use Hail\Util\ArrayDot;
use Hail\Util\ArrayTrait;

/**
 * HttpRequest provides access scheme for request sent via HTTP.
 *
 */
class Input implements \ArrayAccess
{
	use ArrayTrait;

	protected $request;

	/** @var ArrayDot */
	protected $items = [];

	/** @var bool */
	protected $all = false;

	/** @var ArrayDot */
	protected $del = [];


	public function __construct(Request $request)
	{
		$this->request = $request;
		$this->items = Arrays::dot();
		$this->del = Arrays::dot();
	}

	public function setAll(array $array)
	{
		$this->setMultiple($array);
		$this->all = true;
	}

	/**
	 * @param string $key
	 * @param mixed  $value
	 */
	public function set($key, $value = null)
	{
		if (!$this->all) {
			unset($this->del[$key]);
		}
		$this->items[$key] = $value;
	}

	public function delete($key)
	{
		if (!$this->all) {
			$this->del[$key] = true;
		}
		unset($this->items[$key]);
	}

	/**
	 * @param string|null $key
	 *
	 * @return array|FileUpload|mixed|null|string
	 */
	public function get(string $key = null)
	{
		if ($key === null) {
			return $this->getAll();
		} elseif ($this->all) {
			return $this->items[$key];
		}

		if (isset($this->del[$key])) {
			return null;
		} elseif ($this->items[$key] !== null) {
			return $this->items[$key];
		}

		if ($this->request->isJson()) {
			$return = $this->request->getJson($key);
		} elseif (!$this->request->isMethod('GET')) {
			if (
				strpos(
					$this->request->getHeader('CONTENT-TYPE'),
					'multipart/form-data'
				) === 0
			) {
				$return = $this->request->getFile($key);
			}

			$return = $return ?? $this->request->getPost($key);
		}

		$return = $return ?? $this->request->getQuery($key);

		if ($return !== null) {
			$this->items[$key] = $return;
		}

		return $return;
	}

	public function getAll()
	{
		$return = $this->items->get();
		if ($this->all) {
			return $return;
		}

		if ($this->request->isJson()) {
			$return += $this->request->getJson() ?? [];
		} elseif (!$this->request->isMethod('GET')) {
			if (
				strpos(
					$this->request->getHeader('CONTENT-TYPE'),
					'multipart/form-data'
				) === 0
			) {
				$return += $this->request->getFile() ?? [];
			}

			$return += $this->request->getPost() ?? [];
		}

		$return += $this->request->getQuery();
		if ($this->del !== []) {
			$this->clear(
				$return,
				$this->del->get()
			);
		}

		$this->all = true;
		return $this->items->init($return);
	}

	/**
	 * @param array $array
	 * @param array $del
	 */
	protected function clear(array &$array, array $del)
	{
		foreach ($del as $k => $v) {
			if (is_array($v) && isset($array[$k])) {
				$this->clear($array[$k], $v);
			} else {
				unset($array[$k]);
			}
		}
	}
}
