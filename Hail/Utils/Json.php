<?php
/**
 * Created by IntelliJ IDEA.
 * User: FlyingHail
 * Date: 2016/2/14 0014
 * Time: 12:33
 */

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
	public static function encode($value, $options = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
	{
		$json = json_encode($value, $options);
		static::handleJsonError(json_last_error());
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
		$decode = json_decode($json, $asArray);
		static::handleJsonError(json_last_error());
		return $decode;
	}

	protected static function handleJsonError($lastError)
	{
		if ($lastError === JSON_ERROR_NONE) {
			return;
		}

		static $messages = [
			JSON_ERROR_DEPTH => 'The maximum stack depth has been exceeded.',
			JSON_ERROR_CTRL_CHAR => 'Control character error, possibly incorrectly encoded.',
			JSON_ERROR_SYNTAX => 'Syntax error.',
			JSON_ERROR_STATE_MISMATCH => 'Invalid or malformed JSON.',
			JSON_ERROR_UTF8 => 'Malformed UTF-8 characters, possibly incorrectly encoded.',
		];

		throw new Exception\Json($messages[$lastError] ?? 'Unknown JSON decoding error.');
	}
}