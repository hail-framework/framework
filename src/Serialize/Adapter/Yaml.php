<?php

namespace Hail\Serialize\Adapter;

use Hail\Serialize\AdapterInterface;
use Symfony\Component\Yaml\Yaml as SymfonyYaml;

\defined('YAML_EXTENSION') || \define('YAML_EXTENSION', \extension_loaded('yaml'));

if (YAML_EXTENSION && \ini_get('yaml.decode_php')) {
    \ini_set('yaml.decode_php', 0);
}

class Yaml implements AdapterInterface
{

    public static function getInstance(): AdapterInterface
    {
        if (!YAML_EXTENSION && !class_exists(SymfonyYaml::class)) {
            throw new \LogicException('Yaml parser not loaded');
        }

        return new static();
    }

    public static function constant(string $const)
    {
        if (\defined($const)) {
            return \constant($const);
        }

        throw new \RuntimeException(\sprintf('The constant "%s" is not defined.', $const));
    }

    public function decode(string $value)
    {
        if (YAML_EXTENSION) {
            return \yaml_parse($value, 0, $i, [
                '!php/const' => [self::class, 'constant'],
            ]);
        }

        return SymfonyYaml::parse($value, SymfonyYaml::PARSE_CONSTANT);
    }

    public function decodeFile(string $file)
    {
        if (YAML_EXTENSION) {
            return \yaml_parse_file($file, 0, $i, [
                '!php/const' => [self::class, 'constant'],
            ]);
        }

        return SymfonyYaml::parseFile($file, SymfonyYaml::PARSE_CONSTANT);
    }

    public function encode($value, int $inline = 2, int $indent = 0): string
    {
        if (YAML_EXTENSION) {
            return \yaml_emit($value, YAML_UTF8_ENCODING, YAML_LN_BREAK);
        }

        return SymfonyYaml::dump($value, $inline, $indent, SymfonyYaml::DUMP_EXCEPTION_ON_INVALID_TYPE);
    }
}
