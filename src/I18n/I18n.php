<?php
/**
 * Debian 下 setlocale 只能设置为系统已有的字符集
 * 查看系统字符集：
 * $ locale -a
 *
 * Debian 下添加字符集
 * $ vi /etc/locale.gen
 * 加入需要的字符之后：
 * $ locale-gen
 */

namespace Hail\I18n;

use Hail\I18n\Gettext\GettextTranslator;
use Hail\I18n\Gettext\Translator;
use Hail\I18n\Gettext\TranslatorInterface;

\defined('GETTEXT_EXTENSION') || \define('GETTEXT_EXTENSION', \extension_loaded('gettext'));

/**
 * Class I18n
 *
 * @package Hail\I18n
 */
class I18n
{
    /**
     * @var TranslatorInterface
     */
    public static $translator;

    /**
     * Initialize a new gettext class
     *
     * @param string $directory
     * @param string $domain
     * @param string $locale
     *
     * @return TranslatorInterface|null
     */
    public function translator(string $locale, string $domain, string $directory): ?TranslatorInterface
    {
        $class = GETTEXT_EXTENSION ? GettextTranslator::class : Translator::class;

        $previous = self::$translator;
        self::$translator = new $class($locale, $domain, $directory);

        if ($previous === null) {
            require __DIR__ . DIRECTORY_SEPARATOR . 'helpers.php';
        }

        return $previous;
    }

    public function getTranslator()
    {
        return self::$translator;
    }

    /**
     * @param string $text
     * @param array  $args
     *
     * @return string
     */
    public static function formatTranslate(string $text, array $args): string
    {
        if ($args === []) {
            return $text;
        }

        return \is_array($args[0]) ? \strtr($text, $args[0]) : \vsprintf($text, $args);
    }
}