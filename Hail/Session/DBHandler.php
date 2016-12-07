<?php
namespace Hail\Session;

use Hail\Facades\DB;

/**
 * Class DBHandler
 *
 * @package Hail\Session
 * @author Hao Feng <flyinghail@msn.com>
 */
class DBHandler extends BaseHandler
{
	public function __construct(array $settings = [])
	{
		$settings += [
			'table' => 'sessions',
			'id' => 'id',
			'time' => 'time',
			'data' => 'data',
		];
		parent::__construct($settings);
	}

	/**
	 * {@inheritDoc}
	 */
	public function close()
	{
		return true;
	}

	/**
	 * {@inheritDoc}
	 */
	public function destroy($id)
	{
		$result = DB::delete(
			$this->settings['table'],
			[$this->settings['id'] => $id]
		);
		return $result !== false;
	}

	/**
	 * {@inheritDoc}
	 */
	public function gc($lifetime)
	{
		$result = DB::delete(
			$this->settings['table'], [
				$this->settings['time'] . '[<]' => NOW - $lifetime,
			]
		);
		return $result !== false;
	}

	/**
	 * {@inheritDoc}
	 */
	public function open($path, $name)
	{
		return DB::getInstance() ? true : false;
	}

	/**
	 * {@inheritDoc}
	 */
	public function read($id)
	{
		$result = DB::get([
			'SELECT' => $this->settings['data'],
			'FROM' => $this->settings['table'],
			'WHERE' => [$this->settings['id'] => $id],
		]);

		return $result ?: '';
	}

	/**
	 * {@inheritDoc}
	 */
	public function write($id, $data)
	{
		$result = DB::insert(
			$this->settings['table'], [
			$this->settings['id'] => $id,
			$this->settings['time'] => NOW,
			$this->settings['data'] => $data,
		], 'REPLACE');
		return $result !== false;
	}
}