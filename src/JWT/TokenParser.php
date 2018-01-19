<?php

namespace Hail\JWT;

use Hail\JWT\Exception\BeforeValidException;
use Hail\JWT\Exception\ClaimValidateException;
use Hail\JWT\Exception\ExpiredException;
use Hail\JWT\Exception\SignatureInvalidException;
use Hail\Util\Json;

class TokenParser
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
     * @var Key
     */
    protected $key;

    /**
     * @var \DateTimeInterface
     */
    protected $time;

    /**
     * @param string $jwt
     *
     * @throws \UnexpectedValueException    Provided JWT was invalid
     * @throws SignatureInvalidException    Provided JWT was invalid because the signature verification failed
     * @throws BeforeValidException         Provided JWT is trying to be used before it's eligible as defined by 'nbf'
     * @throws BeforeValidException         Provided JWT is trying to be used before it's been created as defined by 'iat'
     * @throws ExpiredException             Provided JWT has since expired, as defined by the 'exp' claim
     * @throws ClaimValidateException       Provided JWT was invalid because 'iss', 'aud', 'jti', 'subject' verification failed
     */
    public function parse(string $jwt)
    {
        $tks = \explode('.', $jwt);

        if (\count($tks) !== 3) {
            throw new \InvalidArgumentException('The JWT string must have two dots');
        }

        [$encodedHeaders, $encodedClaims, $encodedSignature] = $tks;

        $this->parseHeader($encodedHeaders);

        if (!$this->verify("$encodedHeaders.$encodedClaims", $this->parseSignature($encodedSignature))) {
            throw new SignatureInvalidException('Signature verification failed');
        }

        $this->parseClaims($encodedClaims);
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

        if (isset($headers['alg']) && !isset(Algorithms::ALL[$headers['alg']])) {
            throw new \UnexpectedValueException('Algorithm not allowed');
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

    protected function verify($msg, $signature)
    {
        $alg = $this->getAlgorithm();
        if ($alg === 'none') {
            return $signature === '';
        }

        [$function, $algorithm] = Algorithms::ALL[$alg];

        switch ($function) {
            case 'rsa':
                $key = $this->key->toPublicKey();
                switch (\openssl_verify($msg, $signature, $key, $algorithm)) {
                    case 1:
                        return true;

                    case 0:
                        return false;

                    default:
                        // returns 1 on success, 0 on failure, -1 on error.
                        throw new \DomainException('OpenSSL error: ' . \openssl_error_string());
                }
                break;

            case 'hmac':
            default:
                $key = $this->key->getContent();
                $hash = \hash_hmac($algorithm, $msg, $key, true);

                return \hash_equals($signature, $hash);
        }
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