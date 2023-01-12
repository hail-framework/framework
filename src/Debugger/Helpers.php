<?php

/**
 * This file is part of the Tracy (https://tracy.nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com) Modified by FlyingHail <flyinghail@msn.com>
 */

namespace Hail\Debugger;

use Hail\Facade\Response;
use Hail\Http\Helpers as HttpHelpers;
use Psr\Http\Message\ResponseInterface;


/**
 * Rendering helpers for Debugger.
 */
class Helpers
{

    /**
     * Returns HTML link to editor.
     *
     * @param string   $file
     * @param int|null $line
     *
     * @return string
     */
    public static function editorLink(string $file, int $line = null): string
    {
        $file = \strtr($origFile = $file, Debugger::$editorMapping);
        if ($editor = self::editorUri($origFile, $line)) {
            $file = \str_replace('\\', '/', $file);
            if (\preg_match('#(^[a-z]:)?/.{1,50}$#i', $file, $m) && \strlen($file) > \strlen($m[0])) {
                $file = '...' . $m[0];
            }
            $file = \str_replace('/', DIRECTORY_SEPARATOR, $file);

            return self::formatHtml('<a href="%" title="%">%<b>%</b>%</a>',
                $editor,
                $file . ($line ? ":$line" : ''),
                \rtrim(\dirname($file), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR,
                \basename($file),
                $line ? ":$line" : ''
            );
        }

        return self::formatHtml('<span>%</span>', $file . ($line ? ":$line" : ''));
    }


    /**
     * Returns link to editor.
     *
     * @return string|null
     */
    public static function editorUri(
        string $file,
        int $line = null,
        string $action = 'open',
        string $search = '',
        string $replace = ''
    ): ?string {
        if (Debugger::$editor && $file && ($action === 'create' || is_file($file))) {
            $file = \str_replace('/', DIRECTORY_SEPARATOR, $file);
            $file = \strtr($file, Debugger::$editorMapping);

            return strtr(Debugger::$editor, [
                '%action' => $action,
                '%file' => rawurlencode($file),
                '%line' => $line ?: 1,
                '%search' => rawurlencode($search),
                '%replace' => rawurlencode($replace),
            ]);
        }

        return null;
    }


    public static function formatHtml(string $mask, ...$args): string
    {
        $count = 0;

        return preg_replace_callback('#%#', function () use (&$args, &$count): string {
            return self::escapeHtml($args[$count++]);
        }, $mask);
    }


    public static function escapeHtml($s): string
    {
        return \htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }


    public static function findTrace(array $trace, string $method, int &$index = null): ?string
    {
        $m = \explode('::', $method);
        $function = \end($m);

        foreach ($trace as $i => $item) {
            if (isset($item['function']) && $item['function'] === $function
                && isset($item['class']) === isset($m[1])
                && (!isset($item['class']) || $m[0] === '*' || \is_a($item['class'], $m[0], true))
            ) {
                $index = $i;

                return $item;
            }
        }

        return null;
    }


    /**
     * @return string
     */
    public static function getClass($obj): string
    {
        return \explode("\x00", \get_class($obj))[0];
    }

    public static function fixStack(\Throwable $exception): \Throwable
    {
        if (\function_exists('\xdebug_get_function_stack')) {
            $stack = [];
            $array = \array_slice(\array_reverse(\xdebug_get_function_stack()), 2, -1);
            foreach ($array as $row) {
                $frame = [
                    'file' => $row['file'],
                    'line' => $row['line'],
                    'function' => $row['function'] ?? '*unknown*',
                    'args' => [],
                ];
                if (!empty($row['class'])) {
                    $frame['type'] = isset($row['type']) && $row['type'] === 'dynamic' ? '->' : '::';
                    $frame['class'] = $row['class'];
                }
                $stack[] = $frame;
            }
            $ref = new \ReflectionProperty('Exception', 'trace');
            $ref->setAccessible(true);
            $ref->setValue($exception, $stack);
        }

        return $exception;
    }

    public static function errorTypeToString(int $type): string
    {
        static $types = [
            E_ERROR => 'Fatal Error',
            E_USER_ERROR => 'User Error',
            E_RECOVERABLE_ERROR => 'Recoverable Error',
            E_CORE_ERROR => 'Core Error',
            E_COMPILE_ERROR => 'Compile Error',
            E_PARSE => 'Parse Error',
            E_WARNING => 'Warning',
            E_CORE_WARNING => 'Core Warning',
            E_COMPILE_WARNING => 'Compile Warning',
            E_USER_WARNING => 'User Warning',
            E_NOTICE => 'Notice',
            E_USER_NOTICE => 'User Notice',
            E_STRICT => 'Strict standards',
            E_DEPRECATED => 'Deprecated',
            E_USER_DEPRECATED => 'User Deprecated',
        ];

        return $types[$type] ?? 'Unknown error';
    }


    public static function getSource(): string
    {
        $request = Debugger::getRequest();

        if ($request === null) {
            if (isset($_SERVER['REQUEST_URI'])) {
                return (!empty($_SERVER['HTTPS']) && \strcasecmp($_SERVER['HTTPS'], 'off') ? 'https://' : 'http://')
                    . ($_SERVER['HTTP_HOST'] ?? '')
                    . $_SERVER['REQUEST_URI'];
            }

            return 'CLI (PID: ' . \getmypid() . ')'
                . (empty($_SERVER['argv']) ? '' : ': ' . \implode(' ', $_SERVER['argv']));
        }

        $url = $request->getUri();

        return HttpHelpers::createUriString(
            $url->getScheme(),
            $url->getAuthority(),
            $url->getPath()
        );
    }

    public static function improveException(\Throwable $e): void
    {
        $message = $e->getMessage();
        if (!$e instanceof \Error && !$e instanceof \ErrorException) {
            // do nothing
        } elseif (\preg_match('#^Call to undefined function (\S+\\\\)?(\w+)\(#', $message, $m)) {
            $funcs = \array_merge(\get_defined_functions()['internal'], \get_defined_functions()['user']);
            $hint = self::getSuggestion($funcs, $m[1] . $m[2]) ?: self::getSuggestion($funcs, $m[2]);
            $message = "Call to undefined function $m[2](), did you mean $hint()?";
            $replace = ["$m[2](", "$hint("];

        } elseif (\preg_match('#^Call to undefined method ([\w\\\\]+)::(\w+)#', $message, $m)) {
            $hint = self::getSuggestion(\get_class_methods($m[1]), $m[2]);
            $message .= ", did you mean $hint()?";
            $replace = ["$m[2](", "$hint("];

        } elseif (\preg_match('#^Undefined variable: (\w+)#', $message, $m) && !empty($e->context)) {
            $hint = self::getSuggestion(\array_keys($e->context), $m[1]);
            $message = "Undefined variable $$m[1], did you mean $$hint?";
            $replace = ["$$m[1]", "$$hint"];

        } elseif (\preg_match('#^Undefined property: ([\w\\\\]+)::\$(\w+)#', $message, $m)) {
            $rc = new \ReflectionClass($m[1]);
            $items = \array_diff($rc->getProperties(\ReflectionProperty::IS_PUBLIC),
                $rc->getProperties(\ReflectionProperty::IS_STATIC));
            $hint = self::getSuggestion($items, $m[2]);
            $message .= ", did you mean $$hint?";
            $replace = ["->$m[2]", "->$hint"];

        } elseif (\preg_match('#^Access to undeclared static property: ([\w\\\\]+)::\$(\w+)#', $message, $m)) {
            $rc = new \ReflectionClass($m[1]);
            $items = \array_intersect($rc->getProperties(\ReflectionProperty::IS_PUBLIC),
                $rc->getProperties(\ReflectionProperty::IS_STATIC));
            $hint = self::getSuggestion($items, $m[2]);
            $message .= ", did you mean $$hint?";
            $replace = ["::$$m[2]", "::$$hint"];
        }

        if (isset($hint)) {
            $ref = new \ReflectionProperty($e, 'message');
            $ref->setAccessible(true);
            $ref->setValue($e, $message);
            $e->tracyAction = [
                'link' => self::editorUri($e->getFile(), $e->getLine(), 'fix', $replace[0], $replace[1]),
                'label' => 'fix it',
            ];
        }
    }

    public static function improveError($message, array $context = null)
    {
        if ($context === null) {
            $context = [];
        }

        if (\preg_match('#^Undefined variable: (\w+)#', $message, $m) && $context) {
            $hint = self::getSuggestion(\array_keys($context), $m[1]);
            return $hint ? "Undefined variable $$m[1], did you mean $$hint?" : $message;
        }

        if (\preg_match('#^Undefined property: ([\w\\\\]+)::\$(\w+)#', $message, $m)) {
            $rc = new \ReflectionClass($m[1]);
            $items = \array_diff($rc->getProperties(\ReflectionProperty::IS_PUBLIC),
                $rc->getProperties(\ReflectionProperty::IS_STATIC));
            $hint = self::getSuggestion($items, $m[2]);
            return $hint ? $message . ", did you mean $$hint?" : $message;
        }
        return $message;
    }

    public static function guessClassFile($class)
    {
        $segments = \explode(DIRECTORY_SEPARATOR, $class);
        $res = null;
        $max = 0;
        foreach (\get_declared_classes() as $class) {
            $parts = \explode(DIRECTORY_SEPARATOR, $class);
            foreach ($parts as $i => $part) {
                if (!isset($segments[$i]) || $part !== $segments[$i]) {
                    break;
                }
            }
            if ($i > $max && ($file = (new \ReflectionClass($class))->getFileName())) {
                $max = $i;
                $res = \array_merge(\array_slice(\explode(DIRECTORY_SEPARATOR, $file), 0, $i - \count($parts)),
                    \array_slice($segments, $i));
                $res = \implode(DIRECTORY_SEPARATOR, $res) . '.php';
            }
        }
        return $res;
    }

    /**
     * Finds the best suggestion.
     *
     * @return string|NULL
     */
    public static function getSuggestion(array $items, string $value): ?string
    {
        $best = null;
        $min = (\strlen($value) / 4 + 1) * 10 + .1;
        foreach (\array_unique($items, SORT_REGULAR) as $item) {
            $item = \is_object($item) ? $item->getName() : $item;
            if (($len = \levenshtein($item, $value, 10, 11, 10)) > 0 && $len < $min) {
                $min = $len;
                $best = $item;
            }
        }

        return $best;
    }

    public static function isRedirect(ResponseInterface $response = null): bool
    {
        $location = $response ? $response->getHeader('Location') : Response::getHeader('Location');

        return \array_filter($location) !== [];
    }

    public static function isHtmlMode(ResponseInterface $response = null): bool
    {
        $request = Debugger::getRequest();

        if ($request === null ||
            $request->getHeaderLine('X-Requested-With') !== '' ||
            $request->getHeaderLine('X-Tracy-Ajax') !== '' ||
            \strpos($request->getHeaderLine('Accept'), 'text/html') === false
        ) {
            return false;
        }

        $contentType = $response ? $response->getHeader('Content-Type') : Response::getHeader('Content-Type');
        if ($contentType === []) {
            return true;
        }

        return !\preg_match('#^(?!text/html)#im', \implode("\n", $contentType));
    }

    public static function isAjax(): bool
    {
        $request = Debugger::getRequest();
        if ($request === null || !$request->hasHeader('X-Tracy-Ajax')) {
            return false;
        }

        $value = $request->getHeaderLine('X-Tracy-Ajax');

        return (bool)\preg_match('#^\w{10}\z#', $value);
    }

    /** @internal */
    public static function getNonce(): ?string
    {
        $value = Response::getHeaderLine('Content-Security-Policy');
        if ($value === '' || $value === null) {
            $value = Response::getHeaderLine('Content-Security-Policy-Report-Only');

            if ($value === '' || $value === null) {
                return null;
            }
        }

        return \preg_match('#script-src\s+(?:[^;]+\s)?\'nonce-([\w+/]+=*)\'#m', $value, $m) ? $m[1] : null;
    }
}
