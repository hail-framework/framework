<?php

namespace Hail\Jose;

use Hail\Jose\Exception\BeforeValidException;
use Hail\Jose\Exception\ClaimValidateException;
use Hail\Jose\Exception\ExpiredException;
use Hail\Jose\Exception\SignatureInvalidException;
use Hail\Jose\Key\KeyInterface;
use Hail\Util\Json;

class JWTParser
{
    /**
     * The token header
     *
     * @var array
     */
    protected $headers = [];

    /**
     * The token claim set
     *
     * @var array
     */
    protected $claims = [];

    protected $validation = [];

    /**
     * @var \DateTimeInterface
     */
    protected $time;

    /**
     * @param string $jwt
     * @param KeyInterface|array $keys
     *
     * @throws \UnexpectedValueException    Provided JWT was invalid
     * @throws SignatureInvalidException    Provided JWT was invalid because the signature verification failed
     * @throws BeforeValidException         Provided JWT is trying to be used before it's eligible as defined by 'nbf'
     * @throws BeforeValidException         Provided JWT is trying to be used before it's been created as defined by 'iat'
     * @throws ExpiredException             Provided JWT has since expired, as defined by the 'exp' claim
     * @throws ClaimValidateException       Provided JWT was invalid because 'iss', 'aud', 'jti', 'subject' verification failed
     */
    public function parse(string $jwt, $keys)
    {
        $tks = \explode('.', $jwt);

        if (\count($tks) !== 3) {
            throw new \InvalidArgumentException('The JWT string must have two dots');
        }

        [$encodedHeaders, $encodedClaims, $encodedSignature] = $tks;

        $this->parseHeader($encodedHeaders);

        $algorithm = $this->getAlgorithm();
        if ($this->headers['alg'] === 'none') {
            $signer = new Signer\None();
        } else {
            $class = __NAMESPACE__ . '\\Signer\\' . $algorithm;
            if (!\class_exists($class)) {
                throw new \UnexpectedValueException('Algorithm not allowed');
            }

            $signer = new $class;
        }

        if (\is_array($keys)) {
            if (($kid = $this->getHeader('kid')) !== null) {
                if (!isset($keys[$kid])) {
                    throw new \UnexpectedValueException('"kid" invalid, unable to lookup correct key');
                }

                $key = $keys[$kid];
            } else {
                throw new \UnexpectedValueException('"kid" empty, unable to lookup correct key');
            }
        } else {
            $key = $keys;
        }

        $signature = $this->parseSignature($encodedSignature);

        if (!$signer->verify("$encodedHeaders.$encodedClaims", $signature, $key)) {
            throw new SignatureInvalidException('Signature verification failed');
        }

        $this->parseClaims($encodedClaims);
    }

    /**
     * @param \DateTimeInterface $time
     */
    public function setTime(\DateTimeInterface $time): void
    {
        $this->time = $time;
    }

    public function getTime()
    {
        return $this->time ?? new \DateTimeImmutable('now', \date_default_timezone_get());
    }

    public function validAudience(string $audience)
    {
        $audiences = $this->validation[Claims::AUDIENCE] ?? [];

        if (!\in_array($audience, $audiences, true)) {
            $audiences[] = $audience;

            $this->validation[Claims::AUDIENCE] = $audiences;
        }

        return $this;
    }

    public function validIdentifier(string $id)
    {
        $this->validation[Claims::ID] = $id;

        return $this;
    }

    public function validIssuer(string $id)
    {
        $this->validation[Claims::ISSUER] = $id;

        return $this;
    }

    public function validSubject(string $subject)
    {
        $this->validation[Claims::SUBJECT] = $subject;

        return $this;
    }

    public function getAlgorithm()
    {
        return $this->headers['alg'] ?? 'none';
    }

    public function getHeader(string $name)
    {
        return $this->headers[$name] ?? null;
    }

    /**
     * @return array
     */
    public function getClaims(): array
    {
        return $this->claims;
    }

    public function getClaim(string $name)
    {
        return $this->claims[$name] ?? null;
    }

    public function getAudience()
    {
        return $this->getClaim(Claims::AUDIENCE) ?? [];
    }

    public function getIdentifier()
    {
        return $this->getClaim(Claims::ID);
    }

    public function getIssuer()
    {
        return $this->getClaim(Claims::ISSUER);
    }

    public function getSubject()
    {
        return $this->getClaim(Claims::SUBJECT);
    }

    protected function parseHeader(string $data)
    {
        $headers = $this->decode($data);

        if (null === $headers) {
            throw new \UnexpectedValueException('Invalid header encoding');
        }

        if (isset($headers['enc'])) {
            throw new \UnexpectedValueException('Encryption is not supported yet');
        }

        $this->headers = $headers;
    }

    /**
     * @param string $data
     *
     * @throws \UnexpectedValueException
     * @throws BeforeValidException
     * @throws ExpiredException
     * @throws ClaimValidateException
     */
    protected function parseClaims(string $data)
    {
        $claims = $this->decode($data);

        if (null === $claims) {
            throw new \UnexpectedValueException('Invalid claims encoding');
        }

        if (isset($claims[Claims::AUDIENCE])) {
            $claims[Claims::AUDIENCE] = (array) $claims[Claims::AUDIENCE];
        }

        $time = $this->getTime();

        foreach (
            [
                // Check if the nbf if it is defined. This is the time that the
                // token can actually be used. If it's not yet that time, abort.
                Claims::NOT_BEFORE,
                // Check that this token has been created before 'now'. This prevents
                // using tokens that have been created for later use (and haven't
                // correctly used the nbf claim).
                Claims::ISSUED_AT,
            ] as $claim
        ) {
            if (!isset($claims[$claim])) {
                continue;
            }

            $claims[$claim] = $this->convertDate((string) $claims[$claim]);

            if ($claims[$claim] > $time) {
                throw new BeforeValidException(
                    'Cannot handle token prior to ' . $claims[$claim]->format(\DateTime::ISO8601)
                );
            }
        }

        // Check if this token has expired.
        if (isset($claims[Claims::EXPIRATION_TIME])) {
            $claims[Claims::EXPIRATION_TIME] = $this->convertDate((string) $claims[Claims::EXPIRATION_TIME]);

            if ($time >= $claims[Claims::EXPIRATION_TIME]) {
                throw new ExpiredException('Expired token');
            }
        }

        foreach ([Claims::ISSUER, Claims::SUBJECT, Claims::ID] as $claim) {
            if (isset($this->validation[$claim])) {
                if (!isset($claims[$claim]) || $claims[$claim] !== $this->validation[$claim]) {
                    throw new ClaimValidateException("Invalid $claim");
                }
            }
        }

        if (isset($this->validation[Claims::AUDIENCE])) {
            if (
                !isset($claims[Claims::AUDIENCE]) ||
                [] === \array_intersect($this->validation[Claims::AUDIENCE], $claims[Claims::AUDIENCE])
            ) {
                throw new ClaimValidateException('Invalid aud');
            }
        }

        $this->claims = $claims;
    }

    protected function parseSignature(string $data)
    {
        if ($data === '' || $this->getAlgorithm() === 'none') {
            return '';
        }

        $signature = $this->base64UrlDecode($data);

        if (false === $signature) {
            throw new \UnexpectedValueException('Invalid signature encoding');
        }

        return $signature;
    }

    protected function convertDate(string $value): \DateTimeImmutable
    {
        if (\strpos($value, '.') === false) {
            return new \DateTimeImmutable('@' . $value);
        }

        return \DateTimeImmutable::createFromFormat('U.u', $value);
    }

    protected function decode(string $encoded): array
    {
        return Json::decode(
            $this->base64UrlDecode($encoded)
        );
    }

    protected function base64UrlDecode(string $input): string
    {
        if ($remainder = \strlen($input) % 4) {
            $input .= \str_repeat('=', 4 - $remainder);
        }

        return \base64_decode(\strtr($input, '-_', '+/'));
    }
}