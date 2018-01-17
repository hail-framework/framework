<?php

namespace Hail\Template\Tokenizer;

use Hail\Template\Tokenizer\Exception\TokenMatchingException;
use Hail\Template\Tokenizer\Token\Collection;
use Hail\Template\Tokenizer\Token\TokenInterface;

final class Tokenizer
{
    private const MATCH_CRITERIA = [
        'Php' => '/^\s*<\?php\s/i',
        'PhpEcho' => '/^\s*<\?=/i',
        'Comment' => '/^\s*<!--/',
        'CData' => '/^\s*<!\[CDATA\[/',
        'DocType' => '/^\s*<!DOCTYPE /i',
        'Element' => '/^\s*<[a-z]/i',
        'Text' => '/^[^<]/',
    ];

    /** @var boolean */
    private static $throwOnError = true;

    /** @var string */
    private static $allHtml = '';

    public static function getThrowOnError(): bool
    {
        return self::$throwOnError;
    }

    public static function throwOnError(): void
    {
        self::$throwOnError = true;
    }

    public static function skipOnError(): void
    {
        self::$throwOnError = false;
    }

    public static function parseFile(string $file): Collection
    {
        return self::parse(\file_get_contents($file));
    }

    /**
     * Will parse html into tokens.
     *
     * @param $html string The HTML to tokenize.
     *
     * @return Collection
     * @throws \Hail\Template\Tokenizer\Exception\TokenMatchingException
     */
    public static function parse(string $html): Collection
    {
        self::$allHtml = $html;

        $root = new Collection();
        $root->parse($html);

        return $root;
    }

    public static function getPosition(string $partialHtml): array
    {
        $position = \mb_strrpos(self::$allHtml, $partialHtml);
        $parsedHtml = \mb_substr(self::$allHtml, 0, $position);
        $line = \mb_substr_count($parsedHtml, "\n");
        if ($line === 0) {
            return [
                'line' => 0,
                'position' => $position,
            ];
        }

        $lastNewLinePosition = \mb_strrpos($parsedHtml, "\n");

        return [
            'line' => $line,
            'position' => \mb_strlen(\mb_substr($parsedHtml, $lastNewLinePosition)),
        ];
    }

    /**
     * Factory method to build the correct token.
     *
     * @param string $html
     * @param TokenInterface|null $parent
     * @param array $criteria
     *
     * @return TokenInterface|null
     * @throws TokenMatchingException
     */
    public static function buildFromHtml(
        string $html,
        TokenInterface $parent = null,
        array $criteria = []
    ): ?TokenInterface {
        static $allCriteria;

        if ($criteria === []) {
            if ($allCriteria === null) {
                $allCriteria = \array_keys(self::MATCH_CRITERIA);
            }

            $criteria = $allCriteria;
        }

        foreach ($criteria as $className) {
            if (\preg_match(self::MATCH_CRITERIA[$className], $html)) {
                $fullClassName = __NAMESPACE__ . '\\Token\\' . $className;

                return new $fullClassName($parent);
            }
        }

        // Error condition
        if (self::$throwOnError) {
            throw new TokenMatchingException('Token not match from ' . \mb_substr($html, 0, 20));
        }

        return null;
    }
}
