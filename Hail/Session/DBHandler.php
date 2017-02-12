<?php
namespace Hail\Session;

use Hail\Database\Database;

/**
 * Class DBHandler
 *
 * @package Hail\Session
 * @author Hao Feng <flyinghail@msn.com>
 */
class DBHandler extends BaseHandler
{
	/**
	 * @var Database
	 */
	private $db;

	public function __construct(Database $db, array $settings = [])
	{
		$settings += [
			'table' => 'sessions',
			'id' => 'id',
			'time' => 'time',
			'data' => 'data',
		];
		$this->db = $db;

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

		return $result ?: '';
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