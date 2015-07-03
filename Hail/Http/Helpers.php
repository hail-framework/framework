<?php

/**
 * This file is part of the Nette Framework (http://nette.org)
 * Copyright (c) 2004 David Grudl (http://davidgrudl.com)
 */

namespace Hail\Http;

use Hail\DateTime;

/**
 * Rendering helpers for HTTP.
 *
 * @author     David Grudl
 */
class Helpers
{
	/** @internal */
	const KEY_CHARS = '#^[\x09\x0A\x0D\x20-\x7E\xA0-\x{10FFFF}]*+\z#u';

	/** @internal */
	const VAL_CHARS = '#[^\x09\x0A\x0D\x20-\x7E\xA0-\x{10FFFF}]+#u';

	/**
	 * Returns HTTP valid date format.
	 * @param  string|int|\DateTime
	 * @return string
	 */
	public static function formatDate($time)
	{
		$time = DateTime::from($time);
		$time->setTimezone(new \DateTimeZone('GMT'));
		return $time->format('D, d M Y H:i:s \G\M\T');
	}


	/**
	 * Is IP address in CIDR block?
	 * @return bool
	 */
	public static function ipMatch($ip, $mask)
	{
		list($mask, $size) = explode('/', $mask . '/');
		$tmp = function ($n) { return sprintf('%032b', $n); };
		$ip = implode('', array_map($tmp, unpack('N*', inet_pton($ip))));
		$mask = implode('', array_map($tmp, unpack('N*', inet_pton($mask))));
		$max = strlen($ip);
		if (!$max || $max !== strlen($mask) || $size < 0 || $size > $max) {
			return FALSE;
		}
		return strncmp($ip, $mask, $size === '' ? $max : $size) === 0;
	}


	/**
	 * Removes duplicate cookies from response.
	 * @return void
	 * @internal
	 */
	public static function removeDuplicateCookies()
	{
		if (headers_sent($file, $line) || ini_get('suhosin.cookie.encrypt')) {
			return;
		}

		$flatten = array();
		foreach (headers_list() as $header) {
			if (preg_match('#^Set-Cookie: .+?=#', $header, $m)) {
				$flatten[$m[0]] = $header;
				header_remove('Set-Cookie');
			}
		}
		foreach (array_values($flatten) as $key => $header) {
			header($header, $key === 0);
		}
	}

	/**
	 * @param array $val
	 * @return array
	 */
	public static function getParams($val)
	{
		foreach ($val as $k => $v) {
			if (static::keyCheck($k)) {
				unset($val[$k]);
			} else {
				$val[$k] = static::getParam($v);
			}
		}

		return $val;
	}

	/**
	 * @param string $k
	 * @return bool
	 */
	public static function keyCheck($k)
	{
		return is_string($k) && (!preg_match(self::KEY_CHARS, $k) || preg_last_error());
	}

	/**
	 * @param array|string $v
	 * @return string
	 */
	public static function getParam($v)
	{
		if (is_array($v)) {
			return static::getParams($v);
		} else {
			return (string) preg_replace(self::VAL_CHARS, '', $v);
		}
	}

}
