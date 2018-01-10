<?php
/*
 * This file is part of the CLIFramework package.
 *
 * (c) Yo-An Lin <cornelius.howl@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 */

namespace Hail\Console;

use Hail\Util\SingletonTrait;

/**
 * Console output formatter class
 *
 *
 *   $formatter = Formatter::getInstance();
 *   $text = $formatter->format( 'text', 'styleName' );
 *   $text = $formatter->format( 'text', 'red' );
 *   $text = $formatter->format( 'text', 'green' );
 *
 */
class Formatter
{
    use SingletonTrait;

    // Refactor style builder out.
    protected static $styles = [
        'dim' => ['dim' => 1],
        'red' => ['fg' => 'red'],
        'green' => ['fg' => 'green'],
        'white' => ['fg' => 'white'],
        'yellow' => ['fg' => 'yellow'],
        'strong_red' => ['fg' => 'red', 'bold' => 1],
        'strong_green' => ['fg' => 'green', 'bold' => 1],
        'strong_white' => ['fg' => 'white', 'bold' => 1],
        'strong_yellow' => ['fg' => 'yellow', 'bold' => 1],

        'question' => ['fg' => 'black', 'bg' => 'cyan'],

        'bold' => ['fg' => 'white', 'bold' => 1],
        'underline' => ['fg' => 'white', 'underline' => 1],

        // generic styles for logger
        'info' => ['fg' => 'green'],
        'debug' => ['fg' => 'white'],
        'notice' => ['fg' => 'yellow'],
        'warn' => ['fg' => 'red'],
        'error' => ['fg' => 'white', 'bg'=> 'red', 'bold' => 1],
        'comment' => ['fg' => 'yellow'],

        'done' => ['fg' => 'black', 'bg' => 'green'],
        'success' => ['fg' => 'black', 'bg' => 'green'],
        'fail' => ['fg' => 'black', 'bg' => 'red'],

        'action' => ['fg' => 'white', 'bg' => 'green'],
    ];

    protected static $options = [
        'bold' => 1,
        'dim' => 2,
        'underline' => 4,
        'blink' => 5,
        'reverse' => 7,
        'conceal' => 8,
    ];

    protected static $foreground = [
        'black' => '0;30',
        'dark_gray' => '1;30',
        'red' => '0;31',
        'light_red' => '1;31',
        'green' => '0;32',
        'light_green' => '1;32',
        'brown' => '0;33',
        'yellow' => '1;33',
        'blue' => '0;34',
        'light_blue' => '1;34',
        'purple' => '0;35',
        'light_purple' => '1;35',
        'cyan' => '0;36',
        'light_cyan' => '1;36',
        'light_gray' => '0;37',
        'white' => '1;37',
    ];

    protected static $background = [
        'black' => '40',
        'red' => '41',
        'green' => '42',
        'yellow' => '43',
        'blue' => '44',
        'magenta' => '45',
        'cyan' => '46',
        'white' => '47',
    ];

    protected $supportsColors;

    public function init()
    {
        if (DIRECTORY_SEPARATOR === '\\') {
            $this->supportsColors = false !== getenv('ANSICON') ||
                'ON' === getenv('ConEmuANSI') ||
                'xterm' === getenv('TERM');
        } else {
            $this->supportsColors = function_exists('posix_isatty') && @posix_isatty(STDOUT);
        }
    }

    public function preferRawOutput(): void
    {
        $this->supportsColors = false;
    }

    public function hasStyle(string $style): bool
    {
        return isset(static::$styles[$style]);
    }

    public function getStartMark($style): string
    {
        if (!$this->supportsColors || $style === 'none' || !isset(static::$styles[$style])) {
            return '';
        }

        return $this->buildStartMark(static::$styles[$style]);
    }

    public function getClearMark(): string
    {
        return $this->supportsColors ? "\033[0m" : '';
    }

    protected function buildStartMark(array $style): string
    {
        if (!$this->supportsColors) {
            return '';
        }

        $codes = [];

        if (isset($style['fg'], static::$foreground[$style['fg']])) {
            $codes[] = static::$foreground[$style['fg']];
        }

        if (isset($style['bg'], static::$background[$style['bg']])) {
            $codes[] = static::$background[$style['bg']];
        }

        foreach (static::$options as $option => $value) {
            if (isset($style[$option]) && $style[$option]) {
                $codes[] = $value;
            }
        }

        return "\033[" . implode(';', $codes) . 'm';
    }

    /**
     * Formats a text according to the given style or parameters.
     *
     * @param string $text  The text to style
     * @param string $style A style name
     *
     * @return string The styled text
     */
    public function format($text = '', string $style = 'none')
    {
        return $this->getStartMark($style) . $text . $this->getClearMark();
    }

    public function decorate($text, array $style)
    {
        return $this->buildStartMark($style) . $text . $this->getClearMark();
    }
}
