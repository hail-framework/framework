<?php

namespace Hail\Jose;


use Hail\Jose\Key\KeyInterface;
use Hail\Jose\Signer\SignerInterface;
use Hail\Util\Json;

class JWTBuilder
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
     * @var SignerInterface
     */
    protected $signer;

    public function __construct(array $config = [])
    {
        foreach ($config as $k => $v)
        {
            switch ($k) {
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

    public function setAlgorithm($alg)
    {
        $alg = \strtoupper($alg);
        if ($alg === 'NONE') {
            $alg = 'None';
        }

        $class = __NAMESPACE__ . '\\Signer\\' . $alg;
        if (!\class_exists($class)) {
            throw new \DomainException('Algorithm not supported');
        }

        return $this->setSigner(new $class);
    }

    public function setSigner(SignerInterface $signer): self
    {
        $this->signer = $signer;

        return $this;
    }

    /**
     * @return SignerInterface
     */
    public function getSigner(): SignerInterface
    {
        return $this->signer ?? new Signer\None();
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


    public function getToken(KeyInterface $key)
    {
        if (isset($this->claims[Claims::AUDIENCE][0]) && !isset($this->claims[Claims::AUDIENCE][1])) {
            $this->claims[Claims::AUDIENCE] = $this->claims[Claims::AUDIENCE][0];
        }

        $signer = $this->getSigner();
        $this->headers['alg'] = $signer->getAlgorithm();

        $encodedHeaders = $this->encode($this->headers);
        $encodedClaims = $this->encode($this->claims);

        $payload = $encodedHeaders . '.' . $encodedClaims;
        $signature = $signer->sign($payload, $key);

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