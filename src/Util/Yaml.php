<?php

namespace Hail\Util;

use Hail\Util\Yaml\{
    Parser, Dumper
};
use Hail\Util\Yaml\Exception\ParseException;

\defined('YAML_EXTENSION') || \define('YAML_EXTENSION', \extension_loaded('yaml'));

if (YAML_EXTENSION && \ini_get('yaml.decode_php')) {
    \ini_set('yaml.decode_php', 0);
}

class Yaml
{
    /**
     * @var Parser
     */
    private static $parser;

    private static function getParser(): Parser
    {
        if (self::$parser === null) {
            self::$parser = new Parser();
        }

        return self::$parser;
    }

    /**
     * Parses YAML into a PHP value.
     *
     *  Usage:
     *  <code>
     *   $array = Yaml::parse(file_get_contents('config.yml'));
     *   print_r($array);
     *  </code>
     *
     * @param string $input A string containing YAML
     *
     * @return mixed The YAML converted to a PHP value
     *
     * @throws ParseException If the YAML is not valid
     */
    public static function parse(string $input)
    {
        if (YAML_EXTENSION) {
            return \yaml_parse($input, 0, $i, [
                '!php/const' => [Yaml\Parser::class, 'constant'],
            ]);
        }

        return self::getParser()->parse($input);
    }

    public static function parseFile(string $file)
    {
        if (YAML_EXTENSION) {
            return \yaml_parse_file($file, 0, $i, [
                '!php/const' => [Yaml\Parser::class, 'constant'],
            ]);
        }

        return self::getParser()->parseFile($file);
    }

    /**
     * Dumps a PHP value to a YAML string.
     *
     * The dump method, when supplied with an array, will do its best
     * to convert the array into friendly YAML.
     *
     * @param mixed $input  The PHP value
     * @param int   $inline The level where you switch to inline YAML
     * @param int   $indent The level of indentation (used internally)
     *
     * @return string A YAML string representing the original PHP value
     */
    public static function dump($input, $inline = 2, $indent = 0)
    {
        if (YAML_EXTENSION) {
            return \yaml_emit($input, YAML_UTF8_ENCODING, YAML_LN_BREAK);
        }

        return Dumper::emit($input, $inline, $indent);
    }
}
