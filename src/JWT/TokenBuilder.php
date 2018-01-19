<?php

namespace Hail\JWT;


use Hail\Util\Json;

class TokenBuilder
{
    /**
     * The token header
     *
     * @var array
     */
    protected $headers = ['typ' => 'JWT', 'alg' => 'none'];

    /**
     * The token claim set
     *
     * @var array
     */
    protected $claims = [];

    /**
     * @var Key
     */
    protected $key;

    public function __construct(array $config = [])
    {
        foreach ($config as $k => $v)
        {
            switch ($k) {
                case 'key':
                    $v = (array) $v;
                    $this->setKey(...$v);
                    break;

                case 'alg':
                    $this->setAlgorithm($v);
                    break;

                case Claims::AUDIENCE:
                    $this->claims[Claims::AUDIENCE] = (array) $v;
                    break;

                case Claims::EXPIRATION_TIME:
                    $this->setExpiresAt($v);
                    break;

                case Claims::ID:
                    $this->setIdentifier($v);
                    break;

                case Claims::ISSUED_AT:
                    $this->setIssuedAt($v);
                    break;

                case Claims::ISSUER:
                    $this->setIssuer($v);
                    break;

                case Claims::NOT_BEFORE:
                    $this->setNotBefore($v);
                    break;

                case Claims::SUBJECT:
                    $this->setSubject($v);
                    break;
            }
        }
    }

    public function setKey($key, string $passphrase = '')
    {
        if (\is_string($key)) {
            $key = new Key($key, $passphrase);
        }

        if (!$key instanceof Key) {
            throw new \InvalidArgumentException('Key must be string or Hail\JWT\Key');
        }

        $this->key = $key;

        return $this;
    }

    public function setAlgorithm($algorithm): self
    {
        if (!isset(Algorithms::ALL[$algorithm])) {
            throw new \DomainException('Algorithm not supported');
        }

        $this->headers['alg'] = $algorithm;

        return $this;
    }

    public function setAudience(string $audience)
    {
        $audiences = $this->claims[Claims::AUDIENCE] ?? [];

        if (!\in_array($audience, $audiences, true)) {
            $audiences[] = $audience;

            $this->claims[Claims::AUDIENCE] = $audiences;
        }

        return $this;
    }

    public function setExpiresAt(\DateTimeInterface $expiration)
    {
        $this->claims[Claims::EXPIRATION_TIME] = $this->convertDate($expiration);

        return $this;
    }

    public function setIdentifier(string $id)
    {
        $this->claims[Claims::ID] = $id;

        return $this;
    }

    public function setIssuedAt(\DateTimeInterface $issuedAt)
    {
        $this->claims[Claims::ISSUED_AT] = $this->convertDate($issuedAt);

        return $this;
    }

    public function setIssuer(string $issuer)
    {
        $this->claims[Claims::ISSUER] = $issuer;

        return $this;
    }

    public function setNotBefore(\DateTimeInterface $notBefore)
    {
        $this->claims[Claims::NOT_BEFORE] = $this->convertDate($notBefore);

        return $this;
    }

    public function setSubject(string $subject)
    {
        $this->claims[Claims::SUBJECT] = $subject;

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
        if (\in_array($name, Claims::ALL, true)) {
            throw new \InvalidArgumentException('You should use the correct methods to set registered claims');
        }

        $this->claims[$name] = $value;

        return $this;
    }


    public function getToken()
    {
        if (isset($this->claims[Claims::AUDIENCE][0]) && !isset($this->claims[Claims::AUDIENCE][1])) {
            $this->claims[Claims::AUDIENCE] = $this->claims[Claims::AUDIENCE][0];
        }

        $encodedHeaders = $this->encode($this->headers);
        $encodedClaims = $this->encode($this->claims);

        $payload = $encodedHeaders . '.' . $encodedClaims;
        $signature = $this->sign($payload);

        return $payload . '.' . $this->base64UrlEncode($signature);
    }

    /**
     * Sign a string with a given key and algorithm.
     *
     * @param string              $msg The message to sign
     *
     * @return string An encrypted message
     *
     * @throws \DomainException Unsupported algorithm was specified
     */
    protected function sign($msg)
    {
        $alg = $this->headers['alg'];

        if (isset(Algorithms::ALL[$alg])) {
            [$function, $algorithm] = Algorithms::ALL[$alg];

            switch ($function) {
                case 'hmac':
                    $key = $this->key->getContent();

                    return \hash_hmac($algorithm, $msg, $key, true);

                case 'rsa':
                    $key = $this->key->toPrivateKey();

                    $signature = '';
                    if (!\openssl_sign($msg, $signature, $key, $algorithm)) {
                        throw new \DomainException(
                            'There was an error while creating the signature: ' . \openssl_error_string()
                        );
                    }

                    return $signature;
            }
        }

        return '';
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