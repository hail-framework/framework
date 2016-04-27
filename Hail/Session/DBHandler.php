<?php
/**
 * Created by IntelliJ IDEA.
 * User: FlyingHail
 * Date: 2016/2/15 0015
 * Time: 18:06
 */

namespace Hail\Session;

/**
 * Class DBHandler
 *
 * @package Hail\Session
 */
class DBHandler extends BaseHandler
{
	protected $settings = [
		'table' => 'sessions',
		'id' => 'id',
		'time' => 'time',
		'data' => 'data',
	];

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
		$result = $this->db->delete(
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
		$result = $this->db->delete(
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
		return $this->db ? true : false;
	}

	/**
	 * {@inheritDoc}
	 */
	public function read($id)
	{
		$result = $this->db->get([
			'SELECT' => $this->settings['data'],
			'FROM' => $this->settings['table'],
			'WHERE' => [$this->settings['id'] => $id],
		]);

		return $result;
	}

	/**
	 * {@inheritDoc}
	 */
	public function write($id, $data)
	{
		$result = $this->db->insert(
			$this->settings['table'], [
			$this->settings['id'] => $id,
			$this->settings['time'] => NOW,
			$this->settings['data'] => $data,
		], 'REPLACE');
		return $result !== false;
	}
}