<?php
/**
 * Created by IntelliJ IDEA.
 * User: Hao
 * Date: 2016/12/26 0026
 * Time: 15:41
 */

namespace Hail\I18N;

/**
 * Class I18N
 *
 * @package Hail\I18N
 */
class I18N
{
	protected $gettext;

	public function __construct()
	{
		$this->gettext = extension_loaded('gettext');
	}

	/**
	 * Initialize a new gettext class
	 *
	 * @param string $directory
	 * @param string $domain
	 * @param string $locale
	 */
	public function init(string $directory, string $domain, string $locale)
	{
		if ($this->gettext) {
			setlocale(LC_ALL, $locale . '.utf-8');
			bindtextdomain($domain, $directory);
			textdomain($domain);
		} else {
			Gettext::init($directory, $domain, $locale);
		}
	}

	/**
	 * Return a translated string
	 *
	 * If the translation is not found, the original passed message
	 * will be returned.
	 *
	 * @param string $msg
	 *
	 * @return string Translated message
	 */
	public function gettext(string $msg): string
	{
		return $this->gettext ?
			gettext($msg) :
			Gettext::gettext($msg);
	}

	/**
	 * Return a translated string in it's plural form
	 *
	 * Returns the given $count (e.g second, third,...) plural form of the
	 * given string. If the id is not found and $num == 1 $msg is returned,
	 * otherwise $msg_plural
	 *
	 * @param String  $msg        The message to search for
	 * @param String  $msg_plural A fallback plural form
	 * @param Integer $count      Which plural form
	 *
	 * @return string Translated string
	 */
	public function ngettext(string $msg, string $msg_plural, int $count): string
	{
		return $this->gettext ?
			ngettext($msg, $msg_plural, $count) :
			Gettext::ngettext($msg, $msg_plural, $count);
	}

	/**
	 * Overrides the domain for a single lookup
	 *
	 * If the translation is not found, the original passed message
	 * will be returned.
	 *
	 * @param string $domain The domain to search in
	 * @param string $msg    The message to search for
	 *
	 * @return string Translated string
	 */
	public function dgettext(string $domain, string $msg): string
	{
		return $this->gettext ?
			dgettext($domain, $msg) :
			Gettext::dgettext($domain, $msg);
	}

	/**
	 * Override the current domain for a single plural message lookup
	 *
	 * Returns the given $count (e.g second, third,...) plural form of the
	 * given string. If the id is not found and $num == 1 $msg is returned,
	 * otherwise $msg_plural
	 *
	 * @param string $domain     The domain to search in
	 * @param string $msg        The message to search for
	 * @param string $msg_plural A fallback plural form
	 * @param int    $count      Which plural form
	 *
	 * @return string Translated string
	 */
	public function dngettext(string $domain, string $msg, string $msg_plural, int $count): string
	{
		return $this->gettext ?
			dngettext($domain, $msg, $msg_plural, $count) :
			Gettext::dngettext($domain, $msg, $msg_plural, $count);
	}
}

function _e($msg)
{
	return gettext($msg);
}

function _n($msg, $msg_plural, $count)
{
	return ngettext($msg, $msg_plural, $count);
}

function _d($domain, $msg)
{
	return dgettext($domain, $msg);
}

function _dn($domain, $msg, $msg_plural, $count)
{
	return dngettext($domain, $msg, $msg_plural, $count);
}