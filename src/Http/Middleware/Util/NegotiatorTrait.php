<?php

namespace Hail\Http\Middleware\Util;

use Hail\Http\Helpers;
use InvalidArgumentException;

trait NegotiatorTrait
{
    /**
     * @param string $header     A string containing an `Accept|Accept-*` header.
     * @param array  $priorities A set of server priorities.
     *
     * @return array|null best matching type
     */
    public function getBest($header, array $priorities)
    {
        if (empty($priorities) || !$header) {
            return null;
        }

        // Once upon a time, two `array_map` calls were sitting there, but for
        // some reasons, they triggered `E_WARNING` time to time (because of
        // PHP bug [55416](https://bugs.php.net/bug.php?id=55416). Now, they
        // are gone.
        // See: https://github.com/willdurand/Negotiation/issues/81
        $acceptedHeaders = [];
        foreach ($this->parseHeader($header) as $h) {
            $acceptedHeaders[] = $this->pareseAccept($h);
        }
        $acceptedPriorities = [];
        foreach ($priorities as $p) {
            $acceptedPriorities[] = $this->pareseAccept($p);
        }
        $matches = $this->findMatches($acceptedHeaders, $acceptedPriorities);
        $specificMatches = \array_reduce($matches, 'self::reduce', []);

        \usort($specificMatches, 'self::compare');

        $match = $specificMatches[0];

        return null === $match ? null : $acceptedPriorities[$match->index]['value'];
    }

    /**
     * @param string $header accept header part or server priority
     *
     * @return array Parsed header array
     */
    protected function pareseAccept($header): array
    {
        return self::normalPareseAccept($header);
    }

    protected static function normalPareseAccept($header): array
    {
        [$type, $parameters] = Helpers::parseHeaderValue($header);

        $accept = [
            'quality' => 1.0,
        ];
        if (isset($parameters['q'])) {
            $accept['quality'] = (float) $parameters['q'];
            unset($parameters['q']);
        }

        $type = \strtolower(\trim($type));

        $accept['value'] = $header;
        $accept['type'] = $type;
        $accept['parameters'] = $parameters;

        return $accept;
    }

    /**
     * @param array   $header
     * @param array   $priority
     * @param int $index
     *
     * @return array|null Headers matched
     */
    abstract protected function match(array $header, array $priority, $index);

    /**
     * @param string $header A string that contains an `Accept*` header.
     *
     * @return array[]
     */
    private function parseHeader($header)
    {
        if (!\preg_match_all('/(?:[^,"]*+(?:"[^"]*+")?)+[^,"]*+/', $header, $matches)) {
            throw new InvalidArgumentException('Failed to parse accept header: "' . $header . '"');
        }

        return \array_values(\array_filter(\array_map('\trim', $matches[0])));
    }

    /**
     * @param array[] $headerParts
     * @param array[] $priorities Configured priorities
     *
     * @return array[] Headers matched
     */
    private function findMatches(array $headerParts, array $priorities)
    {
        $matches = [];
        foreach ($priorities as $index => $p) {
            foreach ($headerParts as $h) {
                if (null !== $match = $this->match($h, $p, $index)) {
                    $matches[] = $match;
                }
            }
        }

        return $matches;
    }

    /**
     * @param array $a
     * @param array $b
     *
     * @return int
     */
    public static function compare(array $a, array $b)
    {
        if ($a['quality'] !== $b['quality']) {
            return $a['quality'] > $b['quality'] ? -1 : 1;
        }

        if ($a['index'] !== $b['index']) {
            return $a['index'] > $b['index'] ? 1 : -1;
        }

        return 0;
    }

    /**
     * @param array $carry reduced array
     * @param array $match match to be reduced
     *
     * @return array[]
     */
    public static function reduce(array $carry, array $match)
    {
        if (!isset($carry[$match['index']]) || $carry[$match['index']]['score'] < $match['score']) {
            $carry[$match['index']] = $match;
        }

        return $carry;
    }
}
