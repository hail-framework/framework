<?php
use Hail\I18n\I18n;

if (!function_exists('_')) {
    function _($original)
    {
        return I18n::$translator->gettext($original);
    }
}

if (!function_exists('gettext')) {
    function gettext($original)
    {
        return I18n::$translator->gettext($original);
    }
}

if (!function_exists('ngettext')) {
    function ngettext($original, $plural, $value)
    {
        return I18n::$translator->ngettext($original, $plural, $value);
    }
}

if (!function_exists('dgettext')) {
    function dgettext($domain, $original)
    {
        return I18n::$translator->dgettext($domain, $original);
    }
}

if (!function_exists('dngettext')) {
    function dngettext($domain, $original, $plural, $value)
    {
        return I18n::$translator->dngettext($domain, $original, $plural, $value);
    }
}


/**
 * Returns the translation of a string.
 *
 * @param string $original
 * @param array  ...$args
 *
 * @return string
 */
function __($original, ...$args)
{
    $text = I18n::$translator->gettext($original);

    return I18n::formatTranslate($text, $args);
}

/**
 * Noop, marks the string for translation but returns it unchanged.
 *
 * @param string $original
 *
 * @return string
 */
function noop__($original)
{
    return $original;
}

/**
 * Returns the singular/plural translation of a string.
 *
 * @param string $original
 * @param string $plural
 * @param string $value
 * @param array  ...$args
 *
 * @return string
 */
function n__($original, $plural, $value, ...$args)
{
    $text = I18n::$translator->ngettext($original, $plural, $value);

    return I18n::formatTranslate($text, $args);
}

/**
 * Returns the translation of a string in a specific context.
 *
 * @param string $context
 * @param string $original
 * @param array  ...$args
 *
 * @return string
 */
function p__($context, $original, ...$args)
{
    $text = I18n::$translator->pgettext($context, $original);

    return I18n::formatTranslate($text, $args);
}

/**
 * Returns the translation of a string in a specific domain.
 *
 * @param string $domain
 * @param string $original
 * @param array  ...$args
 *
 * @return string
 */
function d__($domain, $original, ...$args)
{
    $text = I18n::$translator->dgettext($domain, $original);

    return I18n::formatTranslate($text, $args);
}

/**
 * Returns the translation of a string in a specific domain and context.
 *
 * @param string $domain
 * @param string $context
 * @param string $original
 * @param array  ...$args
 *
 * @return string
 */
function dp__($domain, $context, $original, ...$args)
{
    $text = I18n::$translator->dpgettext($domain, $context, $original);

    return I18n::formatTranslate($text, $args);
}

/**
 * Returns the translation of a string in a specific domain and context.
 *
 * @param string $domain
 * @param string $original
 * @param string $plural
 * @param string $value
 * @param array  ...$args
 *
 * @return string
 */
function dn__($domain, $original, $plural, $value, ...$args)
{
    $text = I18n::$translator->dngettext($domain, $original, $plural, $value);

    return I18n::formatTranslate($text, $args);
}

/**
 * Returns the singular/plural translation of a string in a specific context.
 *
 * @param string $context
 * @param string $original
 * @param string $plural
 * @param string $value
 * @param array  ...$args
 *
 * @return string
 */
function np__($context, $original, $plural, $value, ...$args)
{
    $text = I18n::$translator->npgettext($context, $original, $plural, $value);

    return I18n::formatTranslate($text, $args);
}

/**
 * Returns the singular/plural translation of a string in a specific domain and context.
 *
 * @param string $domain
 * @param string $context
 * @param string $original
 * @param string $plural
 * @param string $value
 * @param array  ...$args
 *
 * @return string
 */
function dnp__($domain, $context, $original, $plural, $value, ...$args)
{
    $text = I18n::$translator->dnpgettext($domain, $context, $original, $plural, $value);

    return I18n::formatTranslate($text, $args);
}
