<?php
/**
 * Created by IntelliJ IDEA.
 * User: Hao
 * Date: 2018/1/23 0023
 * Time: 19:38
 */

namespace Hail\Jose;


class Helpers
{
    public static function base64UrlEncode(string $input): string
    {
        return \str_replace('=', '', \strtr(\base64_encode($input), '+/', '-_'));
    }

    public static function base64UrlDecode(string $input): string
    {
        if ($remainder = \strlen($input) % 4) {
            $input .= \str_repeat('=', 4 - $remainder);
        }

        return \base64_decode(\strtr($input, '-_', '+/'));
    }
}