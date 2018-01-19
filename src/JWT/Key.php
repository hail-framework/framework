<?php

namespace Hail\JWT;

final class Key
{
    /**
     * @var string
     */
    private $content;

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
        $this->setContent($content);

        $this->passphrase = $passphrase;
    }

    /**
     * @param string $content
     *
     * @throws \InvalidArgumentException
     */
    private function setContent(string $content): void
    {
        if (\strpos($content, 'file://') === 0) {
            $content = $this->readFile(\substr($content, 7));
        }

        $this->content = $content;
    }

    /**
     * @param string $file
     *
     * @return string
     *
     * @throws \InvalidArgumentException
     */
    private function readFile(string $file): string
    {
        if (!\is_readable($file)) {
            throw new \InvalidArgumentException('You must inform a valid key file');
        }

        return \file_get_contents($file);
    }

    /**
     * @return string
     */
    public function getContent(): string
    {
        return $this->content;
    }

    /**
     * @return resource
     */
    public function toPrivateKey()
    {
        $key = \openssl_get_privatekey($this->content, $this->passphrase);

        return $this->validateKey($key);
    }

    /**
     * @return resource
     */
    public function toPublicKey()
    {
        $key = \openssl_get_publickey($this->content);

        return $this->validateKey($key);
    }

    /**
     * @return resource
     */
    private function validateKey($key)
    {
        if ($key === false) {
            throw new \InvalidArgumentException(
                'It was not possible to parse your key, reason: ' . \openssl_error_string()
            );
        }

        $details = \openssl_pkey_get_details($key);

        if (! isset($details['key']) || $details['type'] !== \OPENSSL_KEYTYPE_RSA) {
            throw new \InvalidArgumentException('This key is not compatible with RSA signatures');
        }

        return $key;
    }
}
