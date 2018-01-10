<?php

/*
 * This file is part of Crawler Detect - the web crawler detection library.
 *
 * (c) Mark Beech <m@rkbee.ch>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Hail\Util;

use Psr\Http\Message\MessageInterface;

class CrawlerDetect
{
    use OptimizeTrait;

    /**
     * The user agent.
     *
     * @var string
     */
    protected $userAgent;

    /**
     * @var MessageInterface
     */
    protected $request;

    /**
     * Store regex matches.
     *
     * @var array
     */
    protected $matches = [];

    /**
     * The compiled regex string.
     *
     * @var string
     */
    protected static $compiledRegex;

    /**
     * The compiled exclusions regex string.
     *
     * @var string
     */
    protected static $compiledExclusions;

    protected static $uaHttpHeaders = [
        // The default User-Agent string.
        'User-Agent',
        // Header can occur on devices using Opera Mini.
        'X-OperaMini-Phone-UA',
        // Vodafone specific header: http://www.seoprinciple.com/mobile-web-community-still-angry-at-vodafone/24/
        'X-Device-User-Agent',
        'X-Original-User-Agent',
        'X-Skyfire-Phone',
        'X-Bolt-Phone-UA',
        'Device-Stock-UA',
        'X-UCBrowser-Device-UA',
        // Sometimes, bots (especially Google) use a genuine user agent, but fill this header in with their email address
        'From',
        'X-Scanner',
        // Seen in use by Netsparker
    ];

    /**
     * Class constructor.
     *
     * @param MessageInterface $request
     */
    public function __construct(MessageInterface $request)
    {
        $this->request = $request;
        $this->setUserAgent();
    }

    /**
     * Set the user agent.
     *
     * @param string $userAgent
     *
     * @return string
     */
    public function setUserAgent(string $userAgent = null)
    {
        if (null === $userAgent) {
            $userAgent = '';
            foreach (static::$uaHttpHeaders as $header) {
                if ($this->request->hasHeader($header)) {
                    $userAgent .= $this->request->getHeaderLine($header) . ' ';
                }
            }
        }

        return $this->userAgent = $userAgent;
    }

    /**
     * Check user agent string against the regex.
     *
     * @param string|null $userAgent
     *
     * @return bool
     */
    public function isCrawler($userAgent = null): bool
    {
        $agent = $userAgent ?: $this->userAgent;

        $agent = \trim(\preg_replace('/' . static::getExclusionsRegex() . '/i', '', $agent));

        if ($agent === '') {
            return false;
        }

        $matches = null;
        $result = \preg_match('/' . static::getCrawlersRegex() . '/i', $agent, $matches);
        $this->matches = $matches ?: null;

        return (bool) $result;
    }

    protected static function getExclusionsRegex(): string
    {
        if (static::$compiledExclusions === null) {
            $file = __DIR__ . '/Data/exclusions.php';

            $data = self::optimizeGet('exclusions', $file);
            if ($data === false) {
                $data = include $file;
                $data = static::compileRegex($data);

                self::optimizeSet('exclusions', $data, $file);
            }

            static::$compiledExclusions = $data;
        }

        return static::$compiledExclusions;
    }

    protected static function getCrawlersRegex(): string
    {
        if (static::$compiledRegex === null) {
            $file = __DIR__ . '/Data/crawlers.php';

            $data = self::optimizeGet('crawlers', $file);
            if ($data === false) {
                $data = include $file;
                $data = static::compileRegex($data);

                self::optimizeSet('crawlers', $data, $file);
            }

            static::$compiledRegex = $data;
        }

        return static::$compiledRegex;
    }

    /**
     * Compile the regex patterns into one regex string.
     *
     * @param array
     *
     * @return string
     */
    protected static function compileRegex($patterns)
    {
        return '(' . \implode('|', $patterns) . ')';
    }

    /**
     * Return the matches.
     *
     * @return string|null
     */
    public function getMatches()
    {
        return $this->matches[0] ?? null;
    }
}
