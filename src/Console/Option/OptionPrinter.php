<?php
/**
 * This file is part of the GetOptionKit package.
 *
 * (c) Yo-An Lin <cornelius.howl@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 */

namespace Hail\Console\Option;

use Hail\Console\Formatter;
use Hail\Util\SingletonTrait;

class OptionPrinter
{
    use SingletonTrait;

    public $screenWidth = 78;

    /**
     * @var Formatter
     */
    public $formatter;

    protected function init(): void
    {
        $this->formatter = Formatter::getInstance();
    }

    /**
     * Render readable spec
     *
     * @param Option $opt
     *
     * @return string
     */
    public function renderOption(Option $opt): string
    {
        $columns = [];
        if ($opt->short) {
            $columns[] = $this->formatter->format('-' . $opt->short, 'strong_white')
                . $this->renderOptionValueHint($opt, false);
        }
        if ($opt->long) {
            $columns[] = $this->formatter->format('--' . $opt->long, 'strong_white')
                . $this->renderOptionValueHint($opt, true);
        }

        return implode(', ', $columns);
    }

    /**
     * @param Option $opt
     * @param bool   $assign
     *
     * @return string
     */
    public function renderOptionValueHint(Option $opt, $assign = true): string
    {
        $n = 'value';
        if ($opt->valueName) {
            $n = $opt->valueName;
        } elseif ($opt->isa) {
            $n = $opt->isa;
        }

        $c = $assign ? '=' : ' ';
        $n = $this->formatter->format($n, 'underline');

        if ($opt->isRequired()) {
            return "$c<$n>";
        }

        if ($opt->isOptional()) {
            return "{$c}[<$n>]";
        }

        return '';
    }

    /**
     * render option descriptions
     *
     * @param OptionCollection $options
     *
     * @return string output
     */
    public function render(OptionCollection $options): string
    {
        # echo "* Available options:\n";
        $lines = [];
        foreach ($options as $option) {
            $c1 = $this->renderOption($option);
            $lines[] = "\t" . $c1;
            $lines[] = wordwrap("\t\t" . $option->desc, $this->screenWidth, "\n\t\t");  # wrap text
            $lines[] = '';
        }

        return implode("\n", $lines);
    }
}
