<?php

namespace Hail\Console\Component\Table;

use InvalidArgumentException;

/**
 * Feature:
 *
 * - Support column wrapping if the cell text is too long.
 * - Table style
 */
class Table
{


    /**
     * @var string[] the rows are expanded by lines
     */
    protected $rows = [];

    /**
     * @var int[] contains the real row index
     */
    protected $rowIndex = [];

    protected $columnWidths = [];

    protected $headers = [];

    protected $style;

    protected $numberOfColumns;

    protected $maxColumnWidth = 50;

    protected $predefinedStyles = [];

    /**
     * Save the mapping of column index => cell attributes
     *
     * [ column index => cell attributes, ... ]
     */
    protected $columnCellAttributes = [];


    /**
     * The default cell attribute
     */
    protected $defaultCellAttribute;


    /**
     * @var bool strip the white spaces from the begining of a
     * string and the end of a string.
     */
    protected $trimSpaces = true;

    protected $trimLeadingSpaces = false;

    protected $trimTrailingSpaces = false;

    protected $footer;

    public function __construct()
    {
        $this->style = new TableStyle;
        $this->defaultCellAttribute = new CellAttribute;
    }

    public function setHeaders(array $headers)
    {
        $this->headers = $headers;

        return $this;
    }

    public function setFooter($footer)
    {
        $this->footer = $footer;

        return $this;
    }

    public function setColumnCellAttribute($colIndex, CellAttribute $cellAttribute)
    {
        $this->columnCellAttributes[$colIndex] = $cellAttribute;
    }

    public function getColumnCellAttribute($colIndex)
    {
        if (isset($this->columnCellAttributes[$colIndex])) {
            return $this->columnCellAttributes[$colIndex];
        }
    }

    public function getDefaultCellAttribute()
    {
        return $this->defaultCellAttribute;
    }

    public function setMaxColumnWidth($width)
    {
        $this->maxColumnWidth = $width;
    }

    /**
     * Gets number of columns for this table.
     *
     * @return int
     */
    private function getNumberOfColumns()
    {
        if (null !== $this->numberOfColumns) {
            return $this->numberOfColumns;
        }

        $columns = [\count($this->headers)];
        foreach ($this->rows as $row) {
            if (!is_array($row)) {
                $row = [];
            }
            $columns[] = \count($row);
        }

        return $this->numberOfColumns = \max($columns);
    }

    public function addRow($row)
    {
        $this->rows[] = $row;

        if ($row instanceof RowSeparator) {
            return $this;
        }

        // $keys = array_keys($this->rows);
        $lastRowIdx = \count($this->rows) - 1;

        $this->rowIndex[$lastRowIdx] = 1;

        $cells = \array_values($row);
        foreach ($cells as $col => $cell) {
            $attribute = $this->defaultCellAttribute;

            $expandAttribute = false;
            if (\is_array($cell)) {
                if ($cell[0] instanceof CellAttribute) {
                    $attribute = $cell[0];
                    unset($cell[0]);
                    $expandAttribute = true;
                }
                $cell = \implode("\n", $cell);
            } elseif (isset($this->columnCellAttributes[$col])) {
                $attribute = $this->columnCellAttributes[$col];
            }

            $lines = $attribute->handleTextOverflow($cell, $this->maxColumnWidth);

            if (\count($lines) === 1) {
                $lines[0] = $attribute->format($lines[0]);
            }

            // Handle extra lines
            $extraRowIdx = $lastRowIdx;
            foreach ($lines as $line) {
                // trim the leading space
                if ($this->trimSpaces) {
                    $line = \trim($line);
                } else {
                    if ($this->trimLeadingSpaces) {
                        $line = \ltrim($line);
                    }
                    if ($this->trimTrailingSpaces) {
                        $line = \rtrim($line);
                    }
                }

                if (isset($this->rows[$extraRowIdx])) {
                    $this->rows[$extraRowIdx][$col] = $expandAttribute ? [$attribute, $line] : $line;
                } else {
                    $this->rows[$extraRowIdx] = [$col => $expandAttribute ? [$attribute, $line] : $line];
                }
                $extraRowIdx++;
            }
        }

        return $this;
    }

    public function getColumnWidth($col)
    {
        if (isset($this->columnWidths[$col])) {
            return $this->columnWidths[$col];
        }

        $lengths = [];
        foreach ($this->rows as $row) {
            if ($row instanceof RowSeparator) {
                continue;
            }
            if (isset($row[$col])) {
                if (\is_array($row[$col])) {
                    if (!isset($row[$col][1])) {
                        throw new InvalidArgumentException('Incorrect cell structure. Expecting [attribute, text].');
                    }
                    $lengths[] = \mb_strlen(\preg_replace('/\033.*?m/', '', $row[$col][1]));
                } else {
                    $lengths[] = \mb_strlen(\preg_replace('/\033.*?m/', '', $row[$col]));
                }
            }
        }

        $headerColumnWidth = isset($this->headers[$col]) ? \mb_strlen($this->headers[$col]) : 0;
        $maxContentWidth = \max($lengths);

        if (empty($lengths) || $headerColumnWidth > $maxContentWidth) {
            return $this->columnWidths[$col] = $headerColumnWidth;
        }

        return $this->columnWidths[$col] = \max($lengths);
    }

    public function renderRow($rowIndex, $row)
    {
        $out = $this->style->verticalBorderChar;
        $columnNumber = $this->getNumberOfColumns();
        for ($c = 0; $c < $columnNumber; $c++) {
            $cell = '';
            if (isset($row[$c])) {
                $cell = $row[$c];
            }

            $out .= $this->renderCell($c, $cell);
            $out .= $this->style->verticalBorderChar;
        }

        if ($rowIndex > 0 && isset($this->rowIndex[$rowIndex]) && $this->style->drawRowSeparator) {
            return $this->renderSeparator() . $out . "\n";
        }

        return $out . "\n";
    }

    public function setStyle($style)
    {
        if (\is_string($style)) {
            if (isset($this->predefinedStyles[$style])) {
                $this->style = $this->predefinedStyles[$style];
            } else {
                throw new InvalidArgumentException("Undefined style $style");
            }
        } else {
            $this->style = $style;
        }

        return $this;
    }

    public function renderSeparator()
    {
        $columnNumber = $this->getNumberOfColumns();
        $out = $this->style->rowSeparatorLeftmostCrossChar;
        for ($c = 0; $c < $columnNumber; $c++) {
            $columnWidth = $this->getColumnWidth($c);
            $out .= \str_repeat($this->style->rowSeparatorBorderChar, $columnWidth + $this->style->cellPadding * 2);

            if ($c + 1 < $columnNumber) {
                $out .= $this->style->rowSeparatorCrossChar;
            } else {
                $out .= $this->style->rowSeparatorRightmostCrossChar;
            }
        }

        return $out . "\n";
    }

    public function renderHeader()
    {
        $out = '';

        if ($this->style->drawTableBorder) {
            $out .= $this->renderSeparator();
        }

        $out .= $this->style->verticalBorderChar;
        $columnNumber = $this->getNumberOfColumns();
        for ($c = 0; $c < $columnNumber; $c++) {
            $cell = '';
            if (isset($this->headers[$c])) {
                $cell = $this->headers[$c];
            }

            $out .= $this->renderCell($c, $cell);
            $out .= $this->style->verticalBorderChar;
        }
        $out .= "\n";
        $out .= $this->renderSeparator();

        return $out;
    }


    public function getTableInnerWidth()
    {
        $columnNumber = $this->getNumberOfColumns();
        $width = 0;
        for ($c = 0; $c < $columnNumber; $c++) {
            $width += $this->getColumnWidth($c) + $this->style->cellPadding * 2 + 1;
        }

        return $width - 1;
    }

    public function renderCell($cellIndex, $cell)
    {
        $attribute = $this->defaultCellAttribute;

        if (\is_array($cell)) {
            if ($cell[0] instanceof CellAttribute) {
                $attribute = $cell[0];
                unset($cell[0]);
            }
            $cell = \implode("\n", $cell);
        } elseif (isset($this->columnCellAttributes[$cellIndex])) {
            $attribute = $this->columnCellAttributes[$cellIndex];
        }

        $width = $this->getColumnWidth($cellIndex);
        if (false !== $encoding = \mb_detect_encoding($cell)) {
            $width += \strlen($cell) - \mb_strlen($cell, $encoding);
        }

        return $attribute->renderCell($cell, $width, $this->style);
    }

    public function renderFooter()
    {
        if (!\is_array($this->footer)) {
            $out = '';
            $width = $this->getTableInnerWidth();
            $out .= $this->renderSeparator();
            $out .= $this->style->verticalBorderChar
                . \str_repeat($this->style->cellPaddingChar, $this->style->cellPadding)
                . \str_pad($this->footer, $width - $this->style->cellPadding * 2)
                . \str_repeat($this->style->cellPaddingChar, $this->style->cellPadding)
                . $this->style->verticalBorderChar . "\n";

            if ($this->style->drawTableBorder) {
                $out .= $this->style->rowSeparatorLeftmostCrossChar .
                    \str_repeat($this->style->rowSeparatorBorderChar, $width) .
                    $this->style->rowSeparatorRightmostCrossChar . "\n";
            }

            return $out;
        }

        $out = '';


        $out .= $this->style->verticalBorderChar;
        $columnNumber = $this->getNumberOfColumns();
        for ($c = 0; $c < $columnNumber; $c++) {
            $cell = '';
            if (isset($this->footer[$c])) {
                $cell = $this->footer[$c];
            }

            $out .= $this->renderCell($c, $cell);
            $out .= $this->style->verticalBorderChar;
        }
        $out .= "\n";

        if ($this->style->drawTableBorder) {
            $out .= $this->renderSeparator();
        }

        return $out;
    }

    public function render()
    {
        $out = '';

        if (!empty($this->headers)) {
            $out .= $this->renderHeader();
        } else {
            $out .= $this->renderSeparator();
        }

        foreach ($this->rows as $rowIndex => $row) {
            if ($row instanceof RowSeparator) {
                $out .= $this->renderSeparator();
            } else {
                $out .= $this->renderRow($rowIndex, $row);
            }
        }

        // Markdown table does not support footer
        if ($this->style && !$this->style instanceof MarkdownTableStyle) {
            if (!empty($this->footer)) {
                $out .= $this->renderFooter();
            } else {
                $out .= $this->renderSeparator();
            }
        }

        return $out;
    }
}
