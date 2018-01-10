<?php

namespace Hail\Util;

/**
 * This is the dotenv class.
 *
 * It's responsible for loading a `.env` file in the given directory and
 * setting the environment vars.
 */
class Env
{
    use OptimizeTrait;

    protected const FILE = '.env';

    /**
     * Load `.env` file in given directory.
     *
     * @param string $path
     */
    public static function load(string $path): void
    {
        $filePath = \absolute_path($path, static::FILE);
        if (!\is_readable($filePath) || !\is_file($filePath)) {
            return;
        }

        $array = self::optimizeGet($path, $filePath);

        if ($array !== false) {
            foreach ($array as $name => $value) {
                static::setEnvironmentVariableInternal($name, $value);
            }
        } else {
            $array = [];

            $lines = static::readLinesFromFile($filePath);
            foreach ($lines as $line) {
                if (!static::isComment($line) && \strpos($line, '=') !== false) {
                    [$name, $value] = static::normaliseEnvironmentVariable($line, null);
                    static::setEnvironmentVariableInternal($name, $value);

                    $array[$name] = $value;
                }
            }

            self::optimizeSet($path, $array, $filePath);
        }
    }

    /**
     * Normalise the given environment variable.
     *
     * Takes value as passed in by developer and:
     * - ensures we're dealing with a separate name and value, breaking apart the name string if needed,
     * - cleaning the value of quotes,
     * - cleaning the name of quotes,
     * - resolving nested variables.
     *
     * @param string $name
     * @param string $value
     *
     * @return array
     *
     * @throws \RuntimeException
     */
    protected static function normaliseEnvironmentVariable(string $name, ?string $value): array
    {
        [$name, $value] = static::splitCompoundStringIntoParts($name, $value);
        $name = static::sanitiseVariableName($name);
        $value = static::sanitiseVariableValue($value);

        $value = static::resolveNestedVariables($value);

        return [$name, $value];
    }

    /**
     * Read lines from the file, auto detecting line endings.
     *
     * @param string $filePath
     *
     * @return array
     */
    protected static function readLinesFromFile(string $filePath): array
    {
        // Read file into an array of lines with auto-detected line endings
        if (($autodetect = \ini_get('auto_detect_line_endings')) !== '1') {
            \ini_set('auto_detect_line_endings', '1');
            $lines = \file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            \ini_set('auto_detect_line_endings', $autodetect);
        } else {
            $lines = \file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        }


        return $lines;
    }

    /**
     * Determine if the line in the file is a comment, e.g. begins with a #.
     *
     * @param string $line
     *
     * @return bool
     */
    protected static function isComment(string $line): bool
    {
        $line = \ltrim($line);

        return isset($line[0]) && $line[0] === '#';
    }

    /**
     * Split the compound string into parts.
     *
     * If the `$name` contains an `=` sign, then we split it into 2 parts, a `name` & `value`
     * disregarding the `$value` passed in.
     *
     * @param string $name
     * @param string $value
     *
     * @return array
     */
    protected static function splitCompoundStringIntoParts(string $name, ?string $value): array
    {
        if (\strpos($name, '=') !== false) {
            [$name, $value] = \array_map('\trim', \explode('=', $name, 2));
        }

        return [$name, $value];
    }

    /**
     * Strips quotes from the environment variable value.
     *
     * @param string $value
     *
     * @throws \RuntimeException
     *
     * @return string
     */
    protected static function sanitiseVariableValue(string $value): string
    {
        $value = \trim($value);
        if (!$value) {
            return $value;
        }

        if (static::beginsWithAQuote($value)) { // value starts with a quote
            $quote = $value[0];
            $regexPattern = \sprintf(
                '/^
                %1$s          # match a quote at the start of the value
                (             # capturing sub-pattern used
                 (?:          # we do not need to capture this
                  [^%1$s\\\\] # any character other than a quote or backslash
                  |\\\\\\\\   # or two backslashes together
                  |\\\\%1$s   # or an escaped quote e.g \"
                 )*           # as many characters that match the previous rules
                )             # end of the capturing sub-pattern
                %1$s          # and the closing quote
                .*$           # and discard any string after the closing quote
                /mx',
                $quote
            );
            $value = \preg_replace($regexPattern, '$1', $value);
            $value = \str_replace(["\\$quote", '\\\\'], [$quote, '\\'], $value);
        } else {
            $parts = \explode(' #', $value, 2);
            $value = \trim($parts[0]);

            // Unquoted values cannot contain whitespace
            if (\preg_match('/\s+/', $value) > 0) {
                throw new \RuntimeException('Dotenv values containing spaces must be surrounded by quotes.');
            }
        }

        return \trim($value);
    }

    /**
     * Resolve the nested variables.
     *
     * Look for ${varname} patterns in the variable value and replace with an
     * existing environment variable.
     *
     * @param string $value
     *
     * @return mixed
     */
    protected static function resolveNestedVariables(string $value)
    {
        if (\strpos($value, '$') !== false) {
            $value = \preg_replace_callback(
                '/\${([a-zA-Z0-9_.]+)}/',
                'static::resolveNestedVariablesCallback',
                $value
            );
        }

        return $value;
    }

    protected static function resolveNestedVariablesCallback(array $matchedPatterns): string
    {
        $nestedVariable = static::getEnvironmentVariable($matchedPatterns[1]);
        if ($nestedVariable === null) {
            return $matchedPatterns[0];
        }

        return $nestedVariable;
    }

    protected static function resolveConstantCallback(array $matchedPatterns): string
    {
        if (\defined($matchedPatterns[1])) {
            return \constant($matchedPatterns[1]);
        }

        return $matchedPatterns[0];
    }


    /**
     * Strips quotes and the optional leading "export " from the environment variable name.
     *
     * @param string $name
     *
     * @return string
     */
    protected static function sanitiseVariableName(string $name): string
    {
        return \trim(\str_replace(['export ', '\'', '"'], '', $name));
    }

    /**
     * Determine if the given string begins with a quote.
     *
     * @param string $value
     *
     * @return bool
     */
    protected static function beginsWithAQuote(string $value): bool
    {
        return isset($value[0]) && ($value[0] === '"' || $value[0] === '\'');
    }

    /**
     * Search the different places for environment variables and return first value found.
     *
     * @param string $name
     *
     * @return string|null
     */
    public static function getEnvironmentVariable(string $name): ?string
    {
        if (isset($_ENV[$name])) {
            return $_ENV[$name];
        }

        if (isset($_SERVER[$name])) {
            return $_SERVER[$name];
        }

        $value = \getenv($name);

        return $value === false ? null : $value; // switch getenv default to null
    }

    /**
     * Set an environment variable.
     *
     * This is done using:
     * - putenv,
     * - $_ENV,
     * - $_SERVER.
     *
     * The environment variable value is stripped of single and double quotes.
     *
     * @param string      $name
     * @param string|null $value
     *
     * @return void
     * @throws \RuntimeException
     */
    public static function setEnvironmentVariable(string $name, string $value = null): void
    {
        [$name, $value] = static::normaliseEnvironmentVariable($name, $value);

        static::setEnvironmentVariableInternal($name, $value);
    }

    protected static function setEnvironmentVariableInternal(string $name, string $value = null): void
    {
        // Don't overwrite existing environment variables
        // Ruby's dotenv does this with `ENV[key] ||= value`.
        if (static::getEnvironmentVariable($name) !== null) {
            return;
        }

        // If PHP is running as an Apache module and an existing
        // Apache environment variable exists, overwrite it
        if (\function_exists('apache_getenv') && \function_exists('apache_setenv') && \apache_getenv($name)) {
            \apache_setenv($name, $value);
        }

        if (\function_exists('putenv')) {
            \putenv("$name=$value");
        }

        $_ENV[$name] = $value;
        $_SERVER[$name] = $value;
    }
}
