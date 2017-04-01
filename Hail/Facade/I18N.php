<?php
namespace Hail\Facade;

use Hail;

/**
 * Class I18N
 *
 * @package Hail\Facade
 *
 * @method static void init(string $directory, string $domain, string $locale)
 * @method static string gettext(string $msg)
 * @method static string dgettext(string $domain, string $msg)
 * @method static string ngettext(string $msg, string $msg_plural, int $count)
 * @method static string dngettext(string $domain, string $msg, string $msg_plural, int $count)
 */
class I18N extends Facade
{
	protected static function instance()
	{
		return new Hail\I18N\I18N();
	}
}