<?php
/*
 * ported from https://github.com/symfony/symfony/blob/master/src/Symfony/Component/HttpFoundation/Session/Storage/Handler/AbstractSessionHandler.php
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Hail\Session\Handler;

/**
 * Class BaseHandler
 *
 * @package Hail\Session
 */
abstract class AbstractHandler implements \SessionHandlerInterface, \SessionUpdateTimestampHandlerInterface
{
    /**
     * @var array
     */
    protected $settings = [];

    /**
     * @type string|null
     */
    private $prefetchId;

    /**
     * @type string|null
     */
    private $prefetchData;

    /**
     * @type string|null
     */
    private $newSessionId;

    /**
     * @type string|null
     */
    private $igbinaryEmptyData;


    public function __construct(array $settings = [])
    {
        if (!isset($settings['lifetime']) || $settings['lifetime'] === 0) {
            $settings['lifetime'] = (int) \ini_get('session.gc_maxlifetime');
        }

        $settings['lifetime'] = $settings['lifetime'] ?: 86400;

        $this->settings = $settings;
    }

    /**
     * {@inheritdoc}
     */
    public function open($savePath, $sessionName)
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function close()
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function gc($lifetime)
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function validateId($sessionId)
    {
        $this->prefetchData = $this->read($sessionId);
        $this->prefetchId = $sessionId;

        return '' !== $this->prefetchData;
    }

    /**
     * {@inheritdoc}
     */
    public function read($sessionId)
    {
        if (null !== $this->prefetchId) {
            $prefetchId = $this->prefetchId;
            $prefetchData = $this->prefetchData;
            $this->prefetchId = $this->prefetchData = null;

            if ($prefetchId === $sessionId || '' === $prefetchData) {
                $this->newSessionId = '' === $prefetchData ? $sessionId : null;

                return $prefetchData;
            }
        }

        $data = $this->doRead($sessionId);
        $this->newSessionId = '' === $data ? $sessionId : null;

        return $data;
    }

    /**
     * {@inheritdoc}
     */
    public function write($sessionId, $data)
    {
        if (null === $this->igbinaryEmptyData) {
            // see https://github.com/igbinary/igbinary/issues/146
            $this->igbinaryEmptyData = \function_exists('\igbinary_serialize') ? \igbinary_serialize([]) : '';
        }

        if ('' === $data || $this->igbinaryEmptyData === $data) {
            return $this->destroy($sessionId);
        }
        $this->newSessionId = null;

        return $this->doWrite($sessionId, $data);
    }

    /**
     * {@inheritdoc}
     */
    public function destroy($sessionId)
    {
        return $this->newSessionId === $sessionId || $this->doDestroy($sessionId);
    }


    /**
     * @param string $sessionId
     *
     * @return string
     */
    abstract protected function doRead($sessionId);

    /**
     * @param string $sessionId
     * @param string $data
     *
     * @return bool
     */
    abstract protected function doWrite($sessionId, $data);

    /**
     * @param string $sessionId
     *
     * @return bool
     */
    abstract protected function doDestroy($sessionId);
}