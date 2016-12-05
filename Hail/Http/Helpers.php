<?php

/**
 * This file is part of the Nette Framework (http://nette.org)
 * Copyright (c) 2004 David Grudl (http://davidgrudl.com) Modified by FlyingHail <flyinghail@msn.com>
 */

namespace Hail\Http;

use Hail\Facades\{
	Response as Res,
	Request as Req,
	Arrays
};

/**
 * Rendering helpers for HTTP.
 *
 */
class Helpers
{
	/** @internal */
	const KEY_CHARS = '#^[\x09\x0A\x0D\x20-\x7E\xA0-\x{10FFFF}]*+\z#u';

	/** @internal */
	const VAL_CHARS = '#[^\x09\x0A\x0D\x20-\x7E\xA0-\x{10FFFF}]+#u';

	protected static $cached = [];

	/**
	 * Attempts to cache the sent entity by its last modification date.
	 *
	 * @param  string|int|\DateTime $lastModified last modified time
	 * @param  string               $etag         strong entity tag validator
	 *
	 * @return bool
	 */
	public static function isModified($lastModified = null, $etag = null)
	{
		if ($lastModified) {
			Res::setHeader('Last-Modified', static::formatDate($lastModified));
		}
		if ($etag) {
			Res::setHeader('ETag', '"' . addslashes($etag) . '"');
		}

		$ifNoneMatch = Req::getHeader('If-None-Match');
		if ($ifNoneMatch === '*') {
			$match = true; // match, check if-modified-since
		} elseif ($ifNoneMatch !== null) {
			$etag = Res::getHeader('ETag');

			if ($etag === null || strpos(' ' . strtr($ifNoneMatch, ",\t", '  '), ' ' . $etag) === false) {
				return true;
			} else {
				$match = true; // match, check if-modified-since
			}
		}

		$ifModifiedSince = Req::getHeader('If-Modified-Since');
		if ($ifModifiedSince !== null) {
			$lastModified = Res::getHeader('Last-Modified');
			if ($lastModified !== null && strtotime($lastModified) <= strtotime($ifModifiedSince)) {
				$match = true;
			} else {
				return true;
			}
		}

		if (empty($match)) {
			return true;
		}

		Res::setCode(Response::S304_NOT_MODIFIED);

		return false;
	}


	/**
	 * Returns HTTP valid date format.
	 *
	 * @param  string|int|\DateTime
	 *
	 * @return string
	 */
	public static function formatDate($time)
	{
		$time = self::createDateTime($time);
		$time->setTimezone(new \DateTimeZone('GMT'));

		return $time->format('D, d M Y H:i:s \G\M\T');
	}

	/**
	 * DateTime object factory.
	 *
	 * @param  string|int|\DateTime
	 *
	 * @return \DateTime
	 */
	public static function createDateTime($time)
	{
		if ($time instanceof \DateTimeInterface) {
			return new \DateTime($time->format('Y-m-d H:i:s'), $time->getTimezone());
		} elseif (is_numeric($time)) {
			// average year in seconds
			if ($time <= 31557600) {
				$time += time();
			}

			return new \DateTime('@' . $time,
				new \DateTimeZone(date_default_timezone_get())
			);
		}

		return new \DateTime($time);
	}

	/**
	 * Is IP address in CIDR block?
	 *
	 * @return bool
	 */
	public static function ipMatch($ip, $mask)
	{
		list($mask, $size) = explode('/', $mask . '/');
		$tmp = function ($n) {
			return sprintf('%032b', $n);
		};
		$ip = implode('', array_map($tmp, unpack('N*', inet_pton($ip))));
		$mask = implode('', array_map($tmp, unpack('N*', inet_pton($mask))));
		$max = strlen($ip);
		if (!$max || $max !== strlen($mask) || (int) $size < 0 || (int) $size > $max) {
			return false;
		}

		return strncmp($ip, $mask, $size === '' ? $max : (int) $size) === 0;
	}


	/**
	 * Removes duplicate cookies from response.
	 *
	 * @return void
	 */
	public static function removeDuplicateCookies()
	{
		if (headers_sent($file, $line) || ini_get('suhosin.cookie.encrypt')) {
			return;
		}

		$flatten = [];
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
	 * @param array       $vars
	 * @param string      $type
	 * @param string|null $key
	 *
	 * @return array|FileUpload|mixed|null|string
	 */
	public static function getParam(array &$vars, string $type, string $key = null)
	{
		if (empty($GLOBALS[$type])) {
			return $key === null ? [] : null;
		} elseif ($key === null) {
			foreach ($GLOBALS[$type] as $k => $v) {
				static::getParam($vars, $type, $k);
			}

			return $vars;
		} elseif (isset($vars[$key])) {
			return $vars[$key];
		} elseif (
			!empty(static::$cached[$type][$type]) &&
			array_key_exists($key, static::$cached[$type][$type])
		) {
			return static::$cached[$type][$key];
		} elseif (static::keyCheck($key)) {
			return self::$cached[$type][$key] = null;
		} elseif ($type === '_FILES') {
			if (($file = static::getFile($GLOBALS[$type][$key])) === null) {
				return self::$cached[$type][$key] = null;
			}

			return $vars[$key] = $file;
		} else {
			$pos = strpos($key, '.');
			$first = $pos === false ? $key : substr($key, 0, $pos);
			if (!isset($GLOBALS[$type][$first])) {
				return self::$cached[$type][$key] = null;
			}

			$val = $vars[$first] = $GLOBALS[$type][$first];
			if ($pos !== false) {
				$val = Arrays::get($vars[$first], substr($key, $pos + 1));
			}
			$val = static::valueCheck($val);

			return self::$cached[$type][$key] = $val;
		}
	}

	/**
	 * @param string $k
	 *
	 * @return bool
	 */
	public static function keyCheck($k)
	{
		return is_string($k) && (!preg_match(static::KEY_CHARS, $k) || preg_last_error());
	}

	/**
	 * @param array|string $val
	 *
	 * @return array|string
	 */
	public static function valueCheck($val)
	{
		if ($val === null) {
			return null;
		} elseif (is_array($val)) {
			foreach ($val as $k => $v) {
				if (static::keyCheck($k)) {
					unset($val[$k]);
				} else {
					$val[$k] = static::valueCheck($v);
				}
			}

			return $val;
		}

		return (string) preg_replace(static::VAL_CHARS, '', $val);
	}

	/**
	 * @param array $v
	 *
	 * @return array|FileUpload|null
	 */
	public static function getFile($v)
	{
		if (is_array($v['name'])) {
			$list = [];
			foreach ($v['name'] as $k => $foo) {
				if (!is_numeric($k) && static::keyCheck($k)) {
					continue;
				}

				$file = static::getFile([
					'name' => $v['name'][$k],
					'type' => $v['type'][$k],
					'size' => $v['size'][$k],
					'tmp_name' => $v['tmp_name'][$k],
					'error' => $v['error'][$k],
				]);

				if (null === $file) {
					continue;
				}

				$list[] = $file;
			}

			return $list;
		} elseif (isset($v['name'])) {
			if (static::keyCheck($v['name'])) {
				$v['name'] = '';
			}
			if ($v['error'] !== UPLOAD_ERR_NO_FILE) {
				return new FileUpload($v);
			}
		}

		return null;
	}

	/**
	 * Converts to web safe characters [a-z0-9-] text.
	 *
	 * @param  string $s        UTF-8 encoding
	 * @param  string $charlist allowed characters
	 * @param  bool   $lower
	 *
	 * @return string
	 */
	public static function webalize($s, $charlist = null, $lower = true)
	{
		$s = static::toAscii($s);
		if ($lower) {
			$s = strtolower($s);
		}
		$s = preg_replace('#[^a-z0-9' . preg_quote($charlist, '#') . ']+#i', '-', $s);
		$s = trim($s, '-');

		return $s;
	}

	/**
	 * Converts to ASCII.
	 *
	 * @param  string  UTF-8 encoding
	 *
	 * @return string  ASCII
	 */
	public static function toAscii($s)
	{
		$s = preg_replace('#[^\x09\x0A\x0D\x20-\x7E\xA0-\x{2FF}\x{370}-\x{10FFFF}]#u', '', $s);
		$s = strtr($s, '`\'"^~?', "\x01\x02\x03\x04\x05\x06");
		$s = str_replace(
			["\xE2\x80\x9E", "\xE2\x80\x9C", "\xE2\x80\x9D", "\xE2\x80\x9A", "\xE2\x80\x98", "\xE2\x80\x99", "\xC2\xB0"],
			["\x03", "\x03", "\x03", "\x02", "\x02", "\x02", "\x04"], $s
		);
		if (class_exists('Transliterator', false) && $transliterator = \Transliterator::create('Any-Latin; Latin-ASCII')) {
			$s = $transliterator->transliterate($s);
		}
		if (ICONV_IMPL === 'glibc') {
			$s = str_replace(
				["\xC2\xBB", "\xC2\xAB", "\xE2\x80\xA6", "\xE2\x84\xA2", "\xC2\xA9", "\xC2\xAE"],
				['>>', '<<', '...', 'TM', '(c)', '(R)'], $s
			);
			$s = @iconv('UTF-8', 'WINDOWS-1250//TRANSLIT//IGNORE', $s); // intentionally @
			$s = strtr($s, "\xa5\xa3\xbc\x8c\xa7\x8a\xaa\x8d\x8f\x8e\xaf\xb9\xb3\xbe\x9c\x9a\xba\x9d\x9f\x9e"
				. "\xbf\xc0\xc1\xc2\xc3\xc4\xc5\xc6\xc7\xc8\xc9\xca\xcb\xcc\xcd\xce\xcf\xd0\xd1\xd2\xd3"
				. "\xd4\xd5\xd6\xd7\xd8\xd9\xda\xdb\xdc\xdd\xde\xdf\xe0\xe1\xe2\xe3\xe4\xe5\xe6\xe7\xe8"
				. "\xe9\xea\xeb\xec\xed\xee\xef\xf0\xf1\xf2\xf3\xf4\xf5\xf6\xf8\xf9\xfa\xfb\xfc\xfd\xfe"
				. "\x96\xa0\x8b\x97\x9b\xa6\xad\xb7",
				"ALLSSSSTZZZallssstzzzRAAAALCCCEEEEIIDDNNOOOOxRUUUUYTsraaaalccceeeeiiddnnooooruuuuyt- <->|-.");
			$s = preg_replace('#[^\x00-\x7F]++#', '', $s);
		} else {
			$s = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s); // intentionally @
		}
		$s = str_replace(['`', "'", '"', '^', '~', '?'], '', $s);

		return strtr($s, "\x01\x02\x03\x04\x05\x06", '`\'"^~?');
	}
}
