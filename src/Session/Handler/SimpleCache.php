<?php

namespace Hail\Session\Handler;

use Psr\SimpleCache\CacheInterface;

/**
 * Class CacheHandler.
 *
 * @author Feng Hao <flyinghail@msn.com>
 */
class SimpleCache extends AbstractHandler
{
    /**
     * @var CacheInterface
     */
    private $cache;

    public function __construct(CacheInterface $cache, array $settings)
    {
        $settings += [
            'prefix' => 'psr16ses_',
        ];

        $this->cache = $cache;

        parent::__construct($settings);
    }


    /**
     * {@inheritdoc}
     */
    public function updateTimestamp($sessionId, $data)
    {
        $key = $this->settings['prefix'] . $sessionId;
        $value = $this->cache->get($key);

        if ($value === null) {
            return false;
        }

        return $this->cache->set($key, $value,
            \DateTime::createFromFormat('U', \time() + $this->settings['lifetime'])
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function doRead($sessionId)
    {
        return $this->cache->get($this->settings['prefix'] . $sessionId, '');
    }

    /**
     * {@inheritdoc}
     */
    protected function doWrite($sessionId, $data)
    {
        return $this->cache->set($this->settings['prefix'] . $sessionId, $data, $this->settings['lifetime']);
    }

    /**
     * {@inheritdoc}
     */
    protected function doDestroy($sessionId)
    {
        return $this->cache->delete($this->settings['prefix'] . $sessionId);
    }
}
