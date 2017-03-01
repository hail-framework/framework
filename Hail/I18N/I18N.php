<?php
/**
 * 有一些 Linux 下 setlocale 只能设置为系统已有的字符集
 * 查看系统字符集：
 * $ locale -a
 *
 * Debian 下添加字符集
 * $ vi /etc/locale.gen
 * 加入需要的字符之后：
 * $ locale-gen
 */
namespace Hail\I18N {
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
			$locale .= '.UTF-8';
			if ($this->gettext) {
				if (defined('LC_MESSAGES')) {
					setlocale(LC_MESSAGES, $locale); // Linux
				} else {
					putenv("LC_MESSAGES=$locale"); // windows
				}

				bindtextdomain($domain, $directory);
				textdomain($domain);
				bind_textdomain_codeset($domain, 'UTF-8');
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
			return gettext($msg);
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
			return ngettext($msg, $msg_plural, $count);
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
			return dgettext($domain, $msg);
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
			return dngettext($domain, $msg, $msg_plural, $count);
		}
	}
}

namespace {
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
}