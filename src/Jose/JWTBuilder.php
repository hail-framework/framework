<?php

namespace Hail\Jose;


use Hail\Jose\Key\KeyInterface;
use Hail\Util\Json;

class JWTBuilder
{
    /**
     * The token header
     *
     * @var array
     */
    protected $headers = [
        'typ' => 'JWT',
        'alg' => Signer::NONE
    ];

    /**
     * The token claim set
     *
     * @var array
     */
    protected $claims = [];

    /**
     * @var Signer
     */
    protected $signer;

    protected $key;
    protected $passphrase = '';

    public function __construct(array $config = [])
    {
        foreach ($config as $k => $v)
        {
            switch ($k) {
                case 'alg':
                    $this->setAlgorithm($v);
                    break;

                case 'key':
                    $v = (array) $v;
                    $this->setKey(...$v);
                    break;

                case RegisteredClaims::AUDIENCE:
                    $this->claims[RegisteredClaims::AUDIENCE] = (array) $v;
                    break;

                case RegisteredClaims::EXPIRATION_TIME:
                    $this->setExpiresAt($v);
                    break;

                case RegisteredClaims::ID:
                    $this->setIdentifier($v);
                    break;

                case RegisteredClaims::ISSUED_AT:
                    $this->setIssuedAt($v);
                    break;

                case RegisteredClaims::ISSUER:
                    $this->setIssuer($v);
                    break;

                case RegisteredClaims::NOT_BEFORE:
                    $this->setNotBefore($v);
                    break;

                case RegisteredClaims::SUBJECT:
                    $this->setSubject($v);
                    break;
            }
        }
    }

    public function setAlgorithm($alg)
    {
        $this->headers['alg'] = Signer::supported($alg);

        return $this;
    }

    public function setKey(string $key, string $passphrase = '')
    {
        $this->key = $key;
        $this->passphrase = $passphrase;
    }

    /**
     * @return Signer
     */
    public function getSigner(): Signer
    {
        if ($this->signer === null) {
            $this->signer = new Signer($this->headers['alg'], $this->key, $this->passphrase);
        }

        return $this->signer;
    }

    public function setAudience(string $audience)
    {
        $audiences = $this->claims[RegisteredClaims::AUDIENCE] ?? [];

        if (!\in_array($audience, $audiences, true)) {
            $audiences[] = $audience;

            $this->claims[RegisteredClaims::AUDIENCE] = $audiences;
        }

        return $this;
    }

    public function setExpiresAt(\DateTimeInterface $expiration)
    {
        $this->claims[RegisteredClaims::EXPIRATION_TIME] = $this->convertDate($expiration);

        return $this;
    }

    public function setIdentifier(string $id)
    {
        $this->claims[RegisteredClaims::ID] = $id;

        return $this;
    }

    public function setIssuedAt(\DateTimeInterface $issuedAt)
    {
        $this->claims[RegisteredClaims::ISSUED_AT] = $this->convertDate($issuedAt);

        return $this;
    }

    public function setIssuer(string $issuer)
    {
        $this->claims[RegisteredClaims::ISSUER] = $issuer;

        return $this;
    }

    public function setNotBefore(\DateTimeInterface $notBefore)
    {
        $this->claims[RegisteredClaims::NOT_BEFORE] = $this->convertDate($notBefore);

        return $this;
    }

    public function setSubject(string $subject)
    {
        $this->claims[RegisteredClaims::SUBJECT] = $subject;

        return $this;
    }

    public function setHeader(string $name, $value): self
    {
        if ($name === 'alg') {
            return $this->setAlgorithm($value);
        }

        $this->headers[$name] = $value;

        return $this;
    }

    public function setClaim(string $name, $value)
    {
        if (\in_array($name, RegisteredClaims::ALL, true)) {
            throw new \InvalidArgumentException('You should use the correct methods to set registered claims');
        }

        $this->claims[$name] = $value;

        return $this;
    }


    public function getToken()
    {
        if (isset($this->claims[RegisteredClaims::AUDIENCE][0]) && !isset($this->claims[RegisteredClaims::AUDIENCE][1])) {
            $this->claims[RegisteredClaims::AUDIENCE] = $this->claims[RegisteredClaims::AUDIENCE][0];
        }

        $encodedHeaders = $this->encode($this->headers);
        $encodedClaims = $this->encode($this->claims);

        $payload = $encodedHeaders . '.' . $encodedClaims;
        $signature = $this->getSigner()->sign($payload);

        return $payload . '.' . $this->base64UrlEncode($signature);
    }

    protected function convertDate(\DateTimeInterface $date)
    {
        $seconds = $date->format('U');
        $microseconds = $date->format('u');

        if ((int) $microseconds === 0) {
            return (int) $seconds;
        }

        return $seconds . '.' . $microseconds;
    }

    protected function encode(array $items): string
    {
        return $this->base64UrlEncode(
            Json::encode($items)
        );
    }

    protected function base64UrlEncode(string $input): string
    {
        return \str_replace('=', '', \strtr(\base64_encode($input), '+/', '-_'));
    }
}