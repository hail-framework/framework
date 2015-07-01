<?php
/**
 * @from https://github.com/Ashrey/PHP-Gettext
 * @Copyright (c) 2014 David Soria Parra Modifiend by Alberto Berroteran and FlyingHail
 */

namespace Hail\I18N;

/**
 * Class Gettext
 * @package Hail\I18N
 */
class Gettext
{
	protected $dir;

	/**
	 * Initialize a new gettext class
	 *
	 * @param string $directory
	 * @param string $domain
	 * @param string $locale
	 */
	public function init($directory, $domain, $locale)
	{
		setlocale(LC_ALL, $locale . '.utf-8');
		bindtextdomain($domain, $directory);
		textdomain($domain);

		$this->dir = $directory;
	}

	/**
	 * Return a translated string
	 *
	 * If the translation is not found, the original passed message
	 * will be returned.
	 *
	 * @return string Translated message
	 */
	public function gettext($msg)
	{
		return gettext($msg);
	}
	/**
	 * Overrides the domain for a single lookup
	 *
	 * If the translation is not found, the original passed message
	 * will be returned.
	 *
	 * @param string $domain The domain to search in
	 * @param string $msg The message to search for
	 *
	 * @return string Translated string
	 */
	public function dgettext($domain, $msg)
	{
		return dgettext($domain, $msg);
	}
	/**
	 * Return a translated string in it's plural form
	 *
	 * Returns the given $count (e.g second, third,...) plural form of the
	 * given string. If the id is not found and $num == 1 $msg is returned,
	 * otherwise $msg_plural
	 *
	 * @param string $msg The message to search for
	 * @param string $msg_plural A fallback plural form
	 * @param int $count Which plural form
	 *
	 * @return string Translated string
	 */
	public function ngettext($msg, $msg_plural, $count)
	{
		return ngettext($msg, $msg_plural, $count);
	}
	/**
	 * Override the current domain for a single plural message lookup
	 *
	 * Returns the given $count (e.g second, third,...) plural form of the
	 * given string. If the id is not found and $num == 1 $msg is returned,
	 * otherwise $msg_plural
	 *
	 * @param string $domain The domain to search in
	 * @param string $msg The message to search for
	 * @param string $msg_plural A fallback plural form
	 * @param int $count Which plural form
	 *
	 * @return string Translated string
	 */
	public function dngettext($domain, $msg, $msg_plural, $count)
	{
		return dngettext($domain, $msg, $msg_plural, $count);
	}
}