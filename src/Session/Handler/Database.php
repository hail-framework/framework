<?php

namespace Hail\Session\Handler;

use Hail\Database\Database as DB;

/**
 * Class DBHandler
 *
 * @package Hail\Session
 * @author  Feng Hao <flyinghail@msn.com>
 */
class Database extends AbstractHandler
{
    /**
     * @var DB
     */
    private $db;

    /**
     * @var bool Whether gc() has been called
     */
    private $gcCalled = false;

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
     * {@inheritdoc}
     */
    public function updateTimestamp($sessionId, $data)
    {
        $this->db->update($this->settings['table'],
            [$this->settings['time'] => \time()],
            [$this->settings['id'] => $sessionId]
        );

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function close()
    {
        if ($this->gcCalled) {
            $this->gcCalled = false;

            $this->db->delete(
                $this->settings['table'], [
                    $this->settings['time'] . '[<]' => \time() - $this->settings['lifetime'],
                ]
            );
        }

        $this->db = null;

        return true;
    }

    /**
     * {@inheritDoc}
     */
    protected function doDestroy($sessionId)
    {
        $result = $this->db->delete(
            $this->settings['table'],
            [$this->settings['id'] => $sessionId]
        );

        return $result !== false;
    }

    /**
     * {@inheritDoc}
     */
    public function gc($lifetime)
    {
        $this->gcCalled = true;

        return true;
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
    protected function doRead($sessionId)
    {
        $result = $this->db->get([
            'SELECT' => $this->settings['data'],
            'FROM' => $this->settings['table'],
            'WHERE' => [$this->settings['id'] => $sessionId],
        ]);

        return $result ?: '';
    }

    /**
     * {@inheritDoc}
     */
    protected function doWrite($sessionId, $data)
    {
        $result = $this->db->insert(
            $this->settings['table'], [
            $this->settings['id'] => $sessionId,
            $this->settings['time'] => \time(),
            $this->settings['data'] => $data,
        ], 'REPLACE');

        return $result !== false;
    }
}