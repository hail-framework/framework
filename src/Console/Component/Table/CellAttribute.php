<?php

namespace Hail\Console\Component\Table;

use Hail\Console\Ansi\Colors;
use Hail\Console\Formatter;

class CellAttribute
{
    const ALIGN_RIGHT = 1;

    const ALIGN_LEFT = 2;

    const ALIGN_CENTER = 3;

    const WRAP = 1;

    const CLIP = 2;

    const ELLIPSIS = 3;

    protected $alignment = 2;

    protected $formatter;

    protected $textOverflow = CellAttribute::WRAP;

    protected $backgroundColor;

    protected $foregroundColor;

    /*
    protected $style;

    public function __construct(TableStyle $style)
    {
        $this->style = $style;
    }

    public function setStyle(TableStyle $style)
    {
        $this->style = $style;
    }
    */

    public function setAlignment($alignment)
    {
        $this->alignment = $alignment;
    }

    public function setTextOverflow($overflowType)
    {
        $this->textOverflow = $overflowType;
    }

    /**
     * The default cell text formatter
     */
    public function format($cell)
    {
        if ($this->formatter) {
            return ($this->formatter)($cell);
        }

        return $cell;
    }

    public function setBackgroundColor($color)
    {
        $this->backgroundColor = $color;
    }

    public function setForegroundColor($color)
    {
        $this->foregroundColor = $color;
    }

    public function getForegroundColor()
    {
        return $this->foregroundColor; // TODO: fallback to table style
    }

    public function getBackgroundColor()
    {
        return $this->backgroundColor; // TODO: fallback to table style
    }

    /**
     * When inserting rows, we pre-explode the lines to extra rows from Table
     * hence this method is separated for pre-processing..
     */
    public function handleTextOverflow($cell, $maxWidth)
    {
        $lines = explode("\n", $cell);
        if ($this->textOverflow == self::WRAP) {
            $maxLineWidth = max(array_map('mb_strlen', $lines));
            if ($maxLineWidth > $maxWidth) {
                $cell = wordwrap($cell, $maxWidth, "\n");
                // Re-explode the lines
                $lines = explode("\n", $cell);
            }
        } elseif ($this->textOverflow == self::ELLIPSIS) {
            if (mb_strlen($lines[0]) > $maxWidth) {
                $lines = [mb_substr($lines[0], 0, $maxWidth - 2) . '..'];
            }
        } elseif ($this->textOverflow == self::CLIP) {
            if (mb_strlen($lines[0]) > $maxWidth) {
                $lines = [mb_substr($lines[0], 0, $maxWidth)];
            }
        }

        return $lines;
    }

    public function renderCell($cell, $width, $style)
    {
        $out = '';
        $out .= str_repeat($style->cellPaddingChar, $style->cellPadding);
        /*
        if ($this->backgroundColor || $this->foregroundColor) {
            $decoratedCell = Formatter::getInstance()->decorate($cell, [
                'fg' => $this->foregroundColor ?? null,
                'bg' => $this->backgroundColor ?? null,
            ]);
            $width += mb_strlen($decoratedCell) - mb_strlen($cell);
            $cell = $decoratedCell;
        }
        */

        if ($this->alignment === CellAttribute::ALIGN_LEFT) {
            $out .= str_pad($cell, $width, ' '); // default alignment = LEFT
        } elseif ($this->alignment === CellAttribute::ALIGN_RIGHT) {
            $out .= str_pad($cell, $width, ' ', STR_PAD_LEFT);
        } elseif ($this->alignment === CellAttribute::ALIGN_CENTER) {
            $out .= str_pad($cell, $width, ' ', STR_PAD_BOTH);
        } else {
            $out .= str_pad($cell, $width, ' '); // default alignment
        }

        $out .= str_repeat($style->cellPaddingChar, $style->cellPadding);

        if ($this->backgroundColor || $this->foregroundColor) {
            return Formatter::getInstance()->decorate($out, [
                'fg' => $this->foregroundColor ?? null,
                'bg' => $this->backgroundColor ?? null,
            ]);
        }

        return $out;
    }
}
