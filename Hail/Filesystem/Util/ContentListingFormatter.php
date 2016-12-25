<?php

namespace Hail\Filesystem\Util;

use Hail\Filesystem\Util;

/**
 * Class ContentListingFormatter
 *
 * @package Hail\Filesystem\Util
 */
final class ContentListingFormatter
{
    /**
     * @var string
     */
    private static $directory;
    /**
     * @var bool
     */
    private static $recursive;

    /**
     * Format contents listing.
     *
     * @param string $directory
     * @param bool   $recursive
     * @param array $listing
     *
     * @return array
     */
    public static function formatListing(string $directory, bool $recursive, array $listing)
    {
	    self::$directory = $directory;
	    self::$recursive = $recursive;

    	$listing = array_values(
            array_map(
                ['self', 'addPathInfo'],
                array_filter($listing, ['self', 'isEntryOutOfScope'])
            )
        );

        return self::sortListing($listing);
    }

    private static function addPathInfo(array $entry)
    {
        return $entry + Util::pathinfo($entry['path']);
    }

    /**
     * Determine if the entry is out of scope.
     *
     * @param array $entry
     *
     * @return bool
     */
    private static function isEntryOutOfScope(array $entry)
    {
        if (empty($entry['path']) && $entry['path'] !== '0') {
            return false;
        }

        if (self::$recursive) {
            return self::residesInDirectory($entry);
        }

        return self::isDirectChild($entry);
    }

    /**
     * Check if the entry resides within the parent directory.
     *
     * @param $entry
     *
     * @return bool
     */
    private static function residesInDirectory(array $entry)
    {
        if (self::$directory === '') {
            return true;
        }

        return strpos($entry['path'], self::$directory . '/') === 0;
    }

    /**
     * Check if the entry is a direct child of the directory.
     *
     * @param $entry
     *
     * @return bool
     */
    private static function isDirectChild(array $entry)
    {
        return Util::dirname($entry['path']) === self::$directory;
    }

    /**
     * @param array $listing
     *
     * @return array
     */
    private static function sortListing(array $listing)
    {
        usort(
            $listing,
            function ($a, $b) {
                return strcasecmp($a['path'], $b['path']);
            }
        );

        return $listing;
    }
}
