<?php

namespace Hail\Jose\Key\Abstracts;

use Hail\Jose\Key\KeyInterface;

abstract class OpenSSLKey implements KeyInterface
{
    /**
     * @var string
     */
    protected $content;

    /**
     * @var resource
     */
    protected $resource;

    /**
     * @var array
     */
    protected $details;

    /**
     * @param string $content
     */
    public function __construct(string $content)
    {
        $this->content = $content;
    }

    abstract protected function getOpensslKey();
    abstract protected function getOpensslKeyType();
    abstract protected function getOpensslKeyName();
    abstract protected function getJWKMap();

    public function get()
    {
        if ($this->resource === null) {
            $key = $this->getOpensslKey();

            if ($key === false) {
                throw new \InvalidArgumentException(
                    'It was not possible to parse your key, reason: ' . \openssl_error_string()
                );
            }

            $details = \openssl_pkey_get_details($key);

            if (!isset($details['key']) || $details['type'] !== $this->getOpensslKeyType()) {
                throw new \InvalidArgumentException('This key is not compatible with ' . $this->getOpensslKeyName());
            }

            $this->resource = $key;
            $this->details = $details;
        }

        return $this->resource;
    }

    public function toJWK(): array
    {
        if ($this->details === null) {
            $this->get();
        }

        $details = $this->details;

        $joseMap = $this->getJWKMap();
        $name = $this->getOpensslKeyName();

        $jwk = [
            'kty' => \strtoupper($name),
        ];
        foreach ($details[$name] as $opensslName => $value) {
            if (isset($joseMap[$opensslName])) {
                $jwk[$joseMap[$opensslName]] = $value;
            }
        }

        return $jwk;
    }
}