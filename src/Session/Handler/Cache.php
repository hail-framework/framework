<?php

namespace Hail\Session\Handler;

use Psr\Cache\CacheItemPoolInterface;

/**
 * Class CachePoolHandler.
 *
 * @author Feng Hao <flyinghail@msn.com>
 */
class Cache extends AbstractHandler
{
    /**
     * @type CacheItemPoolInterface Cache driver.
     */
    private $cache;

    public function __construct(CacheItemPoolInterface $cache, array $settings)
    {
        $settings += [
            'prefix' => 'psr6ses_',
        ];

        $this->cache = $cache;

        parent::__construct($settings);
    }


    /**
     * {@inheritdoc}
     */
    #[\ReturnTypeWillChange]
    public function updateTimestamp($sessionId, $data)
    {
        $item = $this->cache->getItem($this->settings['prefix'] . $sessionId);
        $item->expiresAt(\DateTime::createFromFormat('U', \time() + $this->settings['lifetime']));

        return $this->cache->save($item);
    }

    /**
     * {@inheritdoc}
     */
    protected function doRead($sessionId)
    {
        $item = $this->cache->getItem($this->settings['prefix'] . $sessionId);

        if ($item->isHit()) {
            return $item->get();
        }

        return '';
    }

    /**
     * {@inheritdoc}
     */
    protected function doWrite($sessionId, $data)
    {
        $item = $this->cache->getItem($this->settings['prefix'] . $sessionId);

        $item->set($data)
            ->expiresAfter($this->settings['lifetime']);

        return $this->cache->save($item);
    }

    /**
     * {@inheritdoc}
     */
    protected function doDestroy($sessionId)
    {
        return $this->cache->deleteItem(
            $this->settings['prefix'] . $sessionId
        );
    }
}
