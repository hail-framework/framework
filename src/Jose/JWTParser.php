<?php

namespace Hail\Jose;

use Hail\Jose\Exception\BeforeValidException;
use Hail\Jose\Exception\ClaimInvalidException;
use Hail\Jose\Exception\ExpiredException;
use Hail\Jose\Exception\SignatureInvalidException;
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
     * @var string|array
     */
    protected $keys;

    public function __construct(array $config = [])
    {
        foreach ($config as $k => $v)
        {
            switch ($k) {
                case 'keys':
                    $this->setKeys($v);
                    break;

                case RegisteredClaims::AUDIENCE:
                    $this->validation[RegisteredClaims::AUDIENCE] = (array) $v;
                    break;

                case RegisteredClaims::ID:
                    $this->validIdentifier($v);
                    break;


                case RegisteredClaims::ISSUER:
                    $this->validIssuer($v);
                    break;


                case RegisteredClaims::SUBJECT:
                    $this->validSubject($v);
                    break;
            }
        }
    }

    /**
     * @param array|string $keys
     */
    public function setKeys($keys): void
    {
        $this->keys = $keys;
    }

    /**
     * @param string $jwt
     *
     * @return array
     *
     * @throws \UnexpectedValueException    Provided JWT was invalid
     * @throws SignatureInvalidException    Provided JWT was invalid because the signature verification failed
     * @throws BeforeValidException         Provided JWT is trying to be used before it's eligible as defined by 'nbf'
     * @throws BeforeValidException         Provided JWT is trying to be used before it's been created as defined by 'iat'
     * @throws ExpiredException             Provided JWT has since expired, as defined by the 'exp' claim
     * @throws ClaimInvalidException       Provided JWT was invalid because 'iss', 'aud', 'jti', 'subject' verification failed
     */
    public function parse(string $jwt): array
    {
        $tks = \explode('.', $jwt);

        if (\count($tks) !== 3) {
            throw new \InvalidArgumentException('The JWT string must have two dots');
        }

        [$encodedHeaders, $encodedClaims, $encodedSignature] = $tks;

        $this->parseHeader($encodedHeaders);

        $keys = $this->keys;
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

        $signer = new Signer($this->getAlgorithm(), $key);
        $signature = $this->parseSignature($encodedSignature);

        if (!$signer->verify($signature, "$encodedHeaders.$encodedClaims")) {
            throw new SignatureInvalidException('Signature verification failed');
        }

        return $this->parseClaims($encodedClaims);
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
        return $this->time ?? new \DateTimeImmutable('now', new \DateTimeZone(\date_default_timezone_get()));
    }

    public function validAudience(string $audience)
    {
        $audiences = $this->validation[RegisteredClaims::AUDIENCE] ?? [];

        if (!\in_array($audience, $audiences, true)) {
            $audiences[] = $audience;

            $this->validation[RegisteredClaims::AUDIENCE] = $audiences;
        }

        return $this;
    }

    public function validIdentifier(string $id)
    {
        $this->validation[RegisteredClaims::ID] = $id;

        return $this;
    }

    public function validIssuer(string $id)
    {
        $this->validation[RegisteredClaims::ISSUER] = $id;

        return $this;
    }

    public function validSubject(string $subject)
    {
        $this->validation[RegisteredClaims::SUBJECT] = $subject;

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
        return $this->getClaim(RegisteredClaims::AUDIENCE) ?? [];
    }

    public function getIdentifier()
    {
        return $this->getClaim(RegisteredClaims::ID);
    }

    public function getIssuer()
    {
        return $this->getClaim(RegisteredClaims::ISSUER);
    }

    public function getSubject()
    {
        return $this->getClaim(RegisteredClaims::SUBJECT);
    }

    protected function parseHeader(string $data): void
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
     * @return array
     *
     * @throws \UnexpectedValueException
     * @throws BeforeValidException
     * @throws ExpiredException
     * @throws ClaimInvalidException
     */
    protected function parseClaims(string $data): array
    {
        $claims = $this->decode($data);

        if (null === $claims) {
            throw new \UnexpectedValueException('Invalid claims encoding');
        }

        if (isset($claims[RegisteredClaims::AUDIENCE])) {
            $claims[RegisteredClaims::AUDIENCE] = (array) $claims[RegisteredClaims::AUDIENCE];
        }

        $time = $this->getTime();

        foreach (
            [
                // Check if the nbf if it is defined. This is the time that the
                // token can actually be used. If it's not yet that time, abort.
                RegisteredClaims::NOT_BEFORE,
                // Check that this token has been created before 'now'. This prevents
                // using tokens that have been created for later use (and haven't
                // correctly used the nbf claim).
                RegisteredClaims::ISSUED_AT,
            ] as $claim
        ) {
            if (!isset($claims[$claim])) {
                continue;
            }

            $claims[$claim] = $this->convertDate((string) $claims[$claim]);

            if ($claims[$claim] > $time) {
                throw new BeforeValidException(
                    'Cannot handle token prior to ' . $claims[$claim]->format(\DateTime::ATOM)
                );
            }
        }

        // Check if this token has expired.
        if (isset($claims[RegisteredClaims::EXPIRATION_TIME])) {
            $claims[RegisteredClaims::EXPIRATION_TIME] = $this->convertDate((string) $claims[RegisteredClaims::EXPIRATION_TIME]);

            if ($time >= $claims[RegisteredClaims::EXPIRATION_TIME]) {
                throw new ExpiredException('Expired token');
            }
        }

        foreach ([RegisteredClaims::ISSUER, RegisteredClaims::SUBJECT, RegisteredClaims::ID] as $claim) {
            if (isset($this->validation[$claim])) {
                if (!isset($claims[$claim]) || $claims[$claim] !== $this->validation[$claim]) {
                    throw new ClaimInvalidException("Invalid $claim");
                }
            }
        }

        if (isset($this->validation[RegisteredClaims::AUDIENCE])) {
            if (
                !isset($claims[RegisteredClaims::AUDIENCE]) ||
                [] === \array_intersect($this->validation[RegisteredClaims::AUDIENCE], $claims[RegisteredClaims::AUDIENCE])
            ) {
                throw new ClaimInvalidException('Invalid aud');
            }
        }

        return $this->claims = $claims;
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