<?php
namespace Hail\Utils;

class Json
{
	/**
	 * Encodes the given value into a JSON string.
	 *
	 * @param $value
	 * @param int $options
	 *
	 * @return string
	 * @throws Exception\Json if there is any encoding error
	 */
	public static function encode($value, $options = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION)
	{
		$json = json_encode($value, $options);
		if ($error = json_last_error()) {
			throw new Exception\Json(json_last_error_msg(), $error);
		}

		if (PHP_VERSION_ID < 70100) {
			$json = str_replace(["\xe2\x80\xa8", "\xe2\x80\xa9"], ['\u2028', '\u2029'], $json);
		}

		return $json;
	}

	/**
	 * Decodes the given JSON string into a PHP data structure.
	 *
	 * @param string $json the JSON string to be decoded
	 * @param boolean $asArray whether to return objects in terms of associative arrays.
	 *
	 * @return mixed the PHP data
	 * @throws Exception\Json if there is any decoding error
	 */
	public static function decode(string $json, $asArray = true)
	{
		$decode = json_decode($json, $asArray, 512, JSON_BIGINT_AS_STRING);
		if ($error = json_last_error()) {
			throw new Exception\Json(json_last_error_msg(), $error);
		}
		return $decode;
	}
}