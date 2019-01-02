<?php

namespace Hail\Util;

use Hail\Util\Exception\JsonException;

class Json
{
    /**
     * Encodes the given value into a JSON string.
     *
     * @param     $value
     * @param int $options
     *
     * @return string
     * @throws JsonException if there is any encoding error
     */
    public function encode(
        $value,
        int $options = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION
    ): string {
        $json = \json_encode($value, $options);
        if (JSON_ERROR_NONE !== ($error = \json_last_error())) {
            throw new JsonException(\json_last_error_msg(), $error);
        }

        return $json;
    }

    /**
     * Decodes the given JSON string into a PHP data structure.
     *
     * @param string $json the JSON string to be decoded
     * @param bool   $asArray whether to return objects in terms of associative arrays.
     *
     * @return mixed the PHP data
     * @throws JsonException if there is any decoding error
     */
    public function decode(string $json, bool $asArray = true)
    {
        $decode = \json_decode($json, $asArray, 512, JSON_BIGINT_AS_STRING);
        if (JSON_ERROR_NONE !== ($error = \json_last_error())) {
            throw new JsonException(\json_last_error_msg(), $error);
        }

        return $decode;
    }
}