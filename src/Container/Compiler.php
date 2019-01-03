<?php

namespace Hail\Container;

class Compiler
{
    protected $config;

    protected $points = [];
    protected $methods = [];

    protected $alias = [];
    protected $abstractAlias = [];

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function compile(): string
    {
        $this->parseServices();

        $code = "<?php\n";
        $code .= "class Container extends Hail\\Container\\Container\n";
        $code .= "{\n";

        $code .= "\tprotected static \$entryPoints = [\n";
        foreach ($this->points as $k => $v) {
            $code .= "\t\t" . $this->classname($k) . " => $v,\n";
        }
        $code .= "\t];\n\n";

        $code .= "\tprotected \$alias = [\n";
        foreach ($this->alias as $k => $v) {
            $k = $this->classname($k);
            $v = $this->classname($v);
            $code .= "\t\t$k => $v,\n";
        }
        $code .= "\t];\n\n";

        $code .= "\tprotected \$abstractAlias = [\n";
        foreach ($this->abstractAlias as $k => $v) {
            $code .= "\t\t" . $this->classname($k) . " => [\n";
            foreach ($v as $n) {
                $code .= "\t\t\t" . $this->classname($n) . ",\n";
            }
            $code .= "\t\t],\n";
        }
        $code .= "\t];\n\n";

        $code .= "\tpublic function get(\$name)\n";
        $code .= "\t{\n";
        $code .= "\t\tif (isset(\$this->active[\$name])) {\n";
        $code .= "\t\t\treturn \$this->values[\$name];\n";
        $code .= "\t\t}\n\n";
        $code .= "\t\tif (isset(static::\$entryPoints[\$name])) {\n";
        $code .= "\t\t\t\$this->active[\$name] = true;\n";
        $code .= "\t\t\treturn \$this->values[\$name] = \$this->{static::\$entryPoints[\$name]}();\n";
        $code .= "\t\t}\n\n";
        $code .= "\t\tif (isset(\$this->alias[\$name])) {\n";
        $code .= "\t\t\t\$this->active[\$name] = true;\n";
        $code .= "\t\t\treturn \$this->values[\$name] = \$this->get(\$this->alias[\$name]);\n";
        $code .= "\t\t}\n\n";
        $code .= "\t\treturn parent::get(\$name);\n";
        $code .= "\t}\n\n";
        $code .= \implode("\n\n", $this->methods) . "\n";
        $code .= '}';

        return $code;
    }

    protected function parseServices(): void
    {
        $services = $this->config ?? [];
        $alias = [];

        foreach ($services as $k => $v) {
            if (\is_string($v)) {
                if ($v[0] === '@') {
                    if (\strpos($v, '->') > 0) {
                        $this->toMethod($k, $this->parseStr($v));
                    } else {
                        $alias[$k] = \substr($v, 1);
                    }
                } else {
                    $factory = $this->parseStrToClass($v);
                    if ($this->isClassname($v)) {
                        $alias[$v] = $k;
                    }
                    $this->toMethod($k, "{$factory}()");
                }

                continue;
            }

            if ($v === []) {
                if ($this->isClassname($k)) {
                    $this->toMethod($k, "new {$k}()");
                }
                continue;
            }

            if (!\is_array($v)) {
                continue;
            }

            if (isset($v['alias'])) {
                $alias[$k] = $v['alias'];
                continue;
            }

            $to = (array) ($v['to'] ?? []);
            if (isset($v['class|to']) && $v['class|to'] !== $k) {
                $to[] = $v['class|to'];
            }

            foreach ($to as $ref) {
                $alias[$ref] = $k;
            }

            $arguments = '';
            if (isset($v['arguments'])) {
                $arguments = $this->parseArguments($v['arguments']);
            }

            $suffix = \array_merge(
                $this->parseProperty($v['property'] ?? []),
                $this->parseCalls($v['calls'] ?? [])
            );

            if (isset($v['factory'])) {
                $factory = $v['factory'];
                if (\is_array($v['factory'])) {
                    [$c, $m] = $v['factory'];
                    $factory = "{$c}::{$m}";
                }

                if (!\is_string($factory)) {
                    continue;
                }
            } elseif (isset($v['class|to'])) {
                $factory = $v['class|to'];
            } elseif (isset($v['class'])) {
                $factory = $v['class'];
            } elseif ($this->isClassname($k)) {
                $factory = $k;
            } else {
                throw new \RuntimeException('Component not defined any build arguments: ' . $k);
            }

            $factory = $this->parseStrToClass($factory);
            $this->toMethod($k, "{$factory}($arguments)", $suffix);
        }

        $this->alias = $alias;
        foreach ($alias as $k => $v) {
            if (!isset($this->abstractAlias[$v])) {
                $this->abstractAlias[$v] = [];
            }

            $this->abstractAlias[$v][] = $k;
        }
    }

    protected function parseArguments(array $args): string
    {
        return \implode(', ', \array_map([$this, 'parseStr'], $args));
    }

    protected function parseProperty(array $props): array
    {
        if ($props === []) {
            return [];
        }

        $return = [];
        foreach ($props as $k => $v) {
            $return[] = $k . ' = ' . $this->parseStr($v);
        }

        return $return;
    }

    protected function parseCalls(array $calls): array
    {
        if ($calls === []) {
            return [];
        }

        $return = [];
        foreach ($calls as $method => $v) {
            $args = '';
            if (\is_array($v)) {
                $args = $this->parseArguments($v);
            }

            $return[] = $method . '(' . $args . ')';
        }

        return $return;
    }

    protected function parseStrToClass(string $str): string
    {
        if ($str[0] === '@') {
            return $this->parseRef(
                \substr($str, 1)
            );
        }

        if (\strpos($str, '::') !== false) {
            [$class, $method] = \explode('::', $str, 2);
            $parts = \explode($method, ':', 2);
            $method = $parts[0];

            if (isset($parts[1])) {
                $method .= $this->parseArgs($parts[1]);
            }

            return "{$class}::{$method}";
        }

        $parts = \explode($str, ':', 2);
        $class = $parts[0];

        if ($this->isClassname($class)) {
            $class = "new $str";
        } else {
            throw new \RuntimeException("Given value can not convert to build function : $str");
        }

        if (isset($parts[1])) {
            $class .= $this->parseArgs($parts[1]);
        }

        return $class;
    }

    protected function parseStr(string $str): string
    {
        if (\is_string($str) &&
            isset($str[0], $str[1]) &&
            $str[0] === '@' &&
            $str[1] !== '@'
        ) {
            return $this->parseRef(
                \substr($str, 1)
            );
        }

        return \var_export($str, true);
    }

    protected function parseRef(string $name): string
    {
        $parts = \explode('->', $name);
        $name = \array_shift($parts);

        $return = '$this->get(' . $this->classname($name) . ')';
        foreach ($parts as $v) {
            $v = \explode(':', $v, 2);
            $return .= '->' . $v[0];
            if (isset($v[1])) {
                $return .= $this->parseArgs($v[1]);
            }
        }

        return $return;
    }

    protected function parseArgs(string $str): string
    {
        if ($str === '') {
            return '()';
        }

        $args = $this->parseArguments(\explode(',', $str));
        return '(' . $args . ')';
    }

    protected function isClassname(string $name): bool
    {
        return (\class_exists($name) || \interface_exists($name) || \trait_exists($name)) && \strtoupper($name[0]) === $name[0];
    }

    protected function classname(string $name): string
    {
        if ($name[0] === '\\') {
            $name = \ltrim($name, '\\');
        }

        if ($this->isClassname($name)) {
            return "$name::class";
        }

        return \var_export($name, true);
    }

    protected function methodName(string $string): string
    {
        if ($string[0] === '\\') {
            $string = \ltrim($string, '\\');
        }

        $name = 'HAIL_';
        if ($this->isClassname($string)) {
            $name .= 'CLASS__';
        } else {
            $name .= 'PARAM__';
        }

        $name .= \str_replace(['\\', '.'], ['__', '_'], $string);

        return $name;
    }

    protected function toPoint(string $name, string $point): void
    {
        $method = $this->methodName($point);
        $this->points[$name] = "'$method'";
    }

    protected function toMethod(string $name, string $return, array $suffix = []): void
    {
        $method = $this->methodName($name);
        $this->points[$name] = "'$method'";

        $code = "\tprotected function {$method}() {\n";
        if ($suffix !== []) {
            $code .= "\t\t\$object = $return;\n";
            $code .= "\t\t\$object->" . \implode(";\n\t\t\$object->", $suffix) . ";\n";
            $return = '$object';
        }

        $code .= "\t\treturn $return;\n";
        $code .= "\t}";

        $this->methods[] = $code;
    }
}
