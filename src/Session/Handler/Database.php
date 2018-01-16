<?php
namespace Hail\Session\Handler;

use Hail\Database\Database as DB;

/**
 * Class DBHandler
 *
 * @package Hail\Session
 * @author Feng Hao <flyinghail@msn.com>
 */
class Database extends BaseHandler
{
	/**
	 * @var DB
	 */
	private $db;

	public function __construct(DB $db, array $settings = [])
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
				$this->settings['time'] . '[<]' => time() - $lifetime,
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
			$this->settings['time'] => \time(),
			$this->settings['data'] => $data,
		], 'REPLACE');
		return $result !== false;
	}
}