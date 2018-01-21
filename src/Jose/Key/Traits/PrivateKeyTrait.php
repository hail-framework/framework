<?php

namespace Hail\Jose\Key\Traits;


trait PrivateKeyTrait
{
    /**
     * @var string
     */
    private $passphrase;

    /**
     * @param string $content
     * @param string $passphrase
     */
    public function __construct(string $content, string $passphrase = '')
    {
        $this->passphrase = $passphrase;

        parent::__construct($content);
    }

    protected function getOpensslKey()
    {
        return \openssl_pkey_get_private($this->content, $this->passphrase);
    }
}