<?php
namespace Hail\Http\Matcher;

use Psr\Http\Message\RequestInterface;

class Accept implements MatcherInterface
{
    use NegativeResultTrait;

    private $accept;

    /**
     * @param string $accept
     */
    public function __construct(string $accept)
    {
        $this->accept = $this->getValue($accept);
    }

    /**
     * {@inheritdoc}
     */
    public function matches(RequestInterface $request): bool
    {
        return (\stripos($request->getHeaderLine('Accept'), $this->accept) !== false) === $this->result;
    }
}