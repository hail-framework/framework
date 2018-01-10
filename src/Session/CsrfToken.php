<?php
/**
 *
 * This file is part of Aura for PHP.
 *
 * @license http://opensource.org/licenses/bsd-license.php BSD
 *
 */

namespace Hail\Session;

/**
 * Cross-site request forgery token tools.
 *
 * @package Aura.Session
 *
 */
class CsrfToken
{
    /**
     * Session segment for values in this class.
     *
     * @var Segment
     *
     */
    protected $segment;

    /**
     * Constructor.
     *
     * @param Segment $segment A segment for values in this class.
     */
    public function __construct(Segment $segment)
    {
        $this->segment = $segment;
        if (!$this->segment->get('value')) {
            $this->regenerateValue();
        }
    }

    /**
     * Checks whether an incoming CSRF token value is valid.
     *
     * @param string $value The incoming token value.
     *
     * @return bool True if valid, false if not.
     *
     */
    public function isValid($value)
    {
        return \hash_equals($value, $this->getValue());
    }

    /**
     * Gets the value of the outgoing CSRF token.
     *
     * @return string
     *
     */
    public function getValue()
    {
        return $this->segment->get('value');
    }

    /**
     * Regenerates the value of the outgoing CSRF token.
     *
     */
    public function regenerateValue()
    {
        $hash = \hash('sha512', \random_bytes(32));
        $this->segment->set('value', $hash);
    }
}
