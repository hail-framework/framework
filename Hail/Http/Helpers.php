<?php

/**
 * This file is part of the Nette Framework (http://nette.org)
 * Copyright (c) 2004 David Grudl (http://davidgrudl.com) Modifiend by FlyingHail <flyinghail@msn.com>
 */

namespace Hail\Http;

use Hail\DITrait;
use Hail\Utils\DateTime;

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

	/**
	 * Attempts to cache the sent entity by its last modification date.
	 * @param  string|int|\DateTime $lastModified last modified time
	 * @param  string $etag strong entity tag validator
	 * @return bool
	 */
	public static function isModified($lastModified = NULL, $etag = NULL)
	{
		if ($lastModified) {
			\Response::setHeader('Last-Modified', self::formatDate($lastModified));
		}
		if ($etag) {
			\Response::setHeader('ETag', '"' . addslashes($etag) . '"');
		}

		$ifNoneMatch = \Request::getHeader('If-None-Match');
		if ($ifNoneMatch === '*') {
			$match = TRUE; // match, check if-modified-since
		} elseif ($ifNoneMatch !== NULL) {
			$etag = \Response::getHeader('ETag');

			if ($etag == NULL || strpos(' ' . strtr($ifNoneMatch, ",\t", '  '), ' ' . $etag) === FALSE) {
				return TRUE;
			} else {
				$match = TRUE; // match, check if-modified-since
			}
		}

		$ifModifiedSince = \Request::getHeader('If-Modified-Since');
		if ($ifModifiedSince !== NULL) {
			$lastModified = \Response::getHeader('Last-Modified');
			if ($lastModified != NULL && strtotime($lastModified) <= strtotime($ifModifiedSince)) {
				$match = TRUE;
			} else {
				return TRUE;
			}
		}

		if (empty($match)) {
			return TRUE;
		}

		\Response::setCode(Response::S304_NOT_MODIFIED);
		return FALSE;
	}


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
		$tmp = function ($n) {
			return sprintf('%032b', $n);
		};
		$ip = implode('', array_map($tmp, unpack('N*', inet_pton($ip))));
		$mask = implode('', array_map($tmp, unpack('N*', inet_pton($mask))));
		$max = strlen($ip);
		if (!$max || $max !== strlen($mask) || (int) $size < 0 || (int) $size > $max) {
			return FALSE;
		}
		return strncmp($ip, $mask, $size === '' ? $max : (int) $size) === 0;
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

	public static function getParams(&$vars, $type, $key = NULL)
	{
		if (NULL === $key) {
			if (empty($GLOBALS[$type])) {
				return [];
			}

			foreach ($GLOBALS[$type] as $k => $v) {
				if (isset($vars[$k]) || static::keyCheck($k)) {
					continue;
				} elseif ($type === '_FILES') {
					$vars[$k] = static::getFile($v);
					if (NULL === $vars[$k]) {
						$vars[$k] = false;
					}
				} else {
					$vars[$k] = static::getParam($v);
				}
			}

			return array_filter($vars);
		} elseif (isset($var[$key])) {
			return false === $var[$key] ? NULL : $var[$key];
		} elseif (isset($GLOBALS[$type][$key])) {
			if (self::keyCheck($key)) {
				$var[$key] = false;
				return NULL;
			}
			if ($type === '_FILES') {
				$file = self::getFile($GLOBALS[$type][$key]);
				$vars[$key] = NULL === $file ? false : $file;
				return $file;
			} else {
				return $var[$key] = self::getParam($GLOBALS[$type][$key]);
			}
		} else {
			$var[$key] = false;
			return NULL;
		}
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
	 * @param array|string $val
	 * @return array|string
	 */
	public static function getParam($val)
	{
		if (is_array($val)) {
			foreach ($val as $k => $v) {
				if (static::keyCheck($k)) {
					unset($val[$k]);
				} else {
					$val[$k] = static::getParam($v);
				}
			}
			return $val;
		} else {
			return (string)preg_replace(self::VAL_CHARS, '', $val);
		}
	}

	/**
	 * @param array $v
	 * @return array|FileUpload|null
	 */
	public static function getFile($v)
	{
		if (is_array($v['name'])) {
			$list = [];
			foreach ($v['name'] as $k => $foo) {
				if (!is_numeric($k) && self::keyCheck($k)) {
					continue;
				}

				$file = self::getFile(array(
					'name' => $v['name'][$k],
					'type' => $v['type'][$k],
					'size' => $v['size'][$k],
					'tmp_name' => $v['tmp_name'][$k],
					'error' => $v['error'][$k],
				));

				if (NULL === $file) {
					continue;
				}

				$list[] = $file;
			}

			return $list;
		} else {
			if (isset($v['name'])) {
				if (self::keyCheck($v['name'])) {
					$v['name'] = '';
				}
				if ($v['error'] !== UPLOAD_ERR_NO_FILE) {
					return new FileUpload($v);
				}
			}

			return NULL;
		}
	}

	/**
	 * Converts to web safe characters [a-z0-9-] text.
	 * @param  string $s UTF-8 encoding
	 * @param  string $charlist allowed characters
	 * @param  bool $lower
	 * @return string
	 */
	public static function webalize($s, $charlist = NULL, $lower = TRUE)
	{
		$s = self::toAscii($s);
		if ($lower) {
			$s = strtolower($s);
		}
		$s = preg_replace('#[^a-z0-9' . preg_quote($charlist, '#') . ']+#i', '-', $s);
		$s = trim($s, '-');
		return $s;
	}

	/**
	 * Converts to ASCII.
	 * @param  string  UTF-8 encoding
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
		$s = str_replace(array('`', "'", '"', '^', '~', '?'), '', $s);
		return strtr($s, "\x01\x02\x03\x04\x05\x06", '`\'"^~?');
	}
}
