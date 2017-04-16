<?php
namespace Hail\Http;

use Hail\Util\Arrays;
use Hail\Util\ArrayDot;
use Hail\Util\ArrayTrait;

/**
 * Class Input
 *
 * @package Hail\Http
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


	public function __construct(ServerRequest $request)
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
	 * @return array|UploadedFile|mixed|null|string
	 */
	public function get(string $key = null)
	{
		if ($key === null) {
			return $this->getAll();
		}

		if ($this->all) {
			return $this->items[$key];
		}

		if (isset($this->del[$key])) {
			return null;
		}

		if ($this->items[$key] !== null) {
			return $this->items[$key];
		}

		if ($this->request->getMethod() !== 'GET') {
			if (
				strpos(
					$this->request->getHeaderLine('Content-Type'),
					'multipart/form-data'
				) !== false
			) {
				$return = $this->request->getUploadedFiles()[$key];
			}

			$return = $return ?? $this->request->getParsedBody()[$key] ?? null;
		}

		$return = $return ?? $this->request->getQueryParams()[$key] ?? null;

		if ($return !== null) {
			$this->items[$key] = $return;
		}

		return $return;
	}

	public function getAll()
	{
		if ($this->all) {
			return $this->items->get();
		}

		$return = [];

		if (!$this->request->getMethod() === 'GET') {
			if (
				strpos(
					$this->request->getHeaderLine('Content-Type'),
					'multipart/form-data'
				) !== false
			) {
				$return += $this->request->getUploadedFiles() ?? [];
			}

			$return += $this->request->getParsedBody() ?? [];
		}

		$return += $this->request->getQueryParams();

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
