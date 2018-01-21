<?php

namespace Hail\Jose\Signer\Abstracts;

use FG\ASN1\Universal\Integer;
use FG\ASN1\Universal\Sequence;
use FG\ASN1\ASNObject;
use Hail\Jose\Key\EcdsaPrivateKey;
use Hail\Jose\Key\EcdsaPublicKey;
use Hail\Jose\Key\KeyInterface;

abstract class Ecdsa extends Signer
{
    public function sign(string $payload, KeyInterface $key): string
    {
        if (!$key instanceof EcdsaPrivateKey) {
            throw new \InvalidArgumentException('This key is not compatible with ECDSA signatures');
        }

        $signature = '';
        if (!\openssl_sign($payload, $signature, $key->get(), $this->method)) {
            throw new \DomainException(
                'There was an error while creating the signature: ' . \openssl_error_string()
            );
        }

        /* @var \FG\ASN1\Universal\Sequence $asn */
        $asn = ASNObject::fromBinary($signature);

        $signature = '';
        foreach ($asn->getChildren() as $child) {
            /* @var \FG\ASN1\Universal\Integer $child */
            $content = $child->getContent();
            $content = \gmp_strval(\gmp_init($content), 16);
            $content = \str_pad($content, $key->getLength(), '0', STR_PAD_LEFT);
            $content = \hex2bin($content);

            $signature .= $content;
        }

        return $signature;
    }

    public function verify(string $expected, string $payload, KeyInterface $key): bool
    {
        if (!$key instanceof EcdsaPublicKey) {
            throw new \InvalidArgumentException('This key is not compatible with ECDSA signatures');
        }

        $signature = \bin2hex($expected);
        $length = $key->getLength();

        $R = \mb_substr($signature, 0, $length, '8bit');
        $S = \mb_substr($signature, $length, null, '8bit');

        $sequence = new Sequence(
            new Integer(\gmp_strval(\gmp_init($R, 16), 10)),
            new Integer(\gmp_strval(\gmp_init($S, 16), 10))
        );

        switch (\openssl_verify($payload, $sequence->getBinary(), $key->get(), $this->method)) {
            case 1:
                return true;

            case 0:
                return false;

            default:
                // returns 1 on success, 0 on failure, -1 on error.
                throw new \DomainException('OpenSSL error: ' . \openssl_error_string());
        }
    }
}