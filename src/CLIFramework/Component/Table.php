<?php
namespace CLIFramework\Component;
use InvalidArgumentException;

use CLIFramework\Component\TableStyle;

use CLIFramework\Component\MarkdownTableStyle;

interface Separator { }

/**
 * RowSeparator is a slight separator for separating distinct rows...
 */
class RowSeparator implements Separator { }

/**
 * TableSeparator is more likely a section separator, the style is customizable.
 */
class TableSeparator implements Separator { }

class CellAttribute { 

    const ALIGN_RIGHT = 1;

    const ALIGN_LEFT = 2;

    const ALIGN_CENTER = 3;

    protected $alignment = 2;

    protected $formatter;

    protected $textOverflow = 'wrap';

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

    public function setFormatter(callable $formatter)
    {
        $this->formatter = $formatter;
    }

    public function setTextOverflow($overflowType)
    {
        $this->textOverflow = $overflowType;
    }

    public function format($cell) { 
        if ($this->formatter) {
            return call_user_func($this->formatter, $cell);
        }
        return $cell;
    }

    public function setBackgroundColor($color) {
        $this->backgroundColor = $color;
    }

    public function setForegroundColor($color) {
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
        $lines = explode("\n",$cell);
        if ($this->textOverflow == 'wrap') {
            // do wrap if need
            $maxLineWidth = max(array_map('mb_strlen', $lines));
            if ($maxLineWidth > $maxWidth) {
                $cell = wordwrap($cell, $maxWidth, "\n");
                // re-explode the lines
                $lines = explode("\n",$cell);
            }
            return $lines;
        } elseif ($this->textOverflow == 'ellipsis') {
            if (mb_strlen($lines[0]) > $maxWidth) {
                return array(mb_substr($lines[0], 0, $maxWidth - 2) . '..');
            }
            return $lines;
        } elseif ($this->textOverflow == 'clip') {
            if (mb_strlen($lines[0]) > $maxWidth) {
                return array(mb_substr($lines[0], 0, $maxWidth));
            }
            return $lines;
        }
        return $lines;
    }

    public function renderCell($cell, $width, $style)
    {
        // XXX: ANSI Color support
        $out = '';
        $out .= str_repeat($style->cellPaddingChar, $style->cellPadding);

        if ($this->formatter) {
            $cell = $this->format($cell);
        }

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
        return $out;
    }
}

class CurrencyCellAttribute extends CellAttribute {

}



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
    protected $rows = array();

    /**
     * @var inteager[] contains the real row index
     */
    protected $rowIndex = array();

    protected $columnWidths = array();

    protected $headers = array();

    protected $style;

    protected $numberOfColumns;

    protected $maxColumnWidth = 50;

    protected $predefinedStyles = array();

    /**
     * Save the mapping of column index => cell attributes
     *
     * [ column index => cell attributes, ... ]
     */
    protected $columnCellAttributes = array();


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

    public function __construct() {
        $this->style = new TableStyle;
        $this->defaultCellAttribute = new CellAttribute;
    }

    public function setHeaders(array $headers) {
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
        $columnCellAttributes[$colIndex] = $cellAttribute;
    }

    public function getColumnCellAttribute($colIndex)
    {
        if (isset($columnCellAttributes[$colIndex])) {
            return $columnCellAttributes[$colIndex];
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

        $columns = array(count($this->headers));
        foreach ($this->rows as $row) {
            $columns[] = count($row);
        }
        return $this->numberOfColumns = max($columns);
    }

    public function addRow($row) {
        $this->rows[] = $row;

        // $keys = array_keys($this->rows);
        $lastRowIdx = count($this->rows) - 1;

        $this->rowIndex[$lastRowIdx] = 1;

        $cells = array_values($row);
        foreach ($cells as $col => $cell) {
            $lines = $this->defaultCellAttribute->handleTextOverflow($cell, $this->maxColumnWidth);

            // Handle extra lines
            $extraRowIdx = $lastRowIdx;
            foreach($lines as $line) {
                // trim the leading space
                if ($this->trimSpaces) {
                    $line = trim($line);
                } else {
                    if ($this->trimLeadingSpaces) {
                        $line = ltrim($line);
                    }
                    if ($this->trimTrailingSpaces) {
                        $line = rtrim($line);
                    }
                }

                if (isset($this->rows[$extraRowIdx])) {
                    $this->rows[$extraRowIdx][ $col ] = $line;
                } else {
                    $this->rows[$extraRowIdx] = array($col => $line);
                }
                $extraRowIdx++;
            }
        }
        return $this;
    }

    public function getColumnWidth($col) {
        $lengths = array();
        foreach($this->rows as $row) {
            if (isset($row[$col])) {
                $lengths[] = mb_strlen($row[$col]);
            }
        }


        return $this->columnWidth[$col] = max($lengths);
    }

    public function renderRow($rowIndex, $row) {
        $out = $this->style->verticalBorderChar;
        $columnNumber = $this->getNumberOfColumns();
        for ($c = 0 ; $c < $columnNumber ; $c++) {
            if (isset($row[$c])) {
                $cell = $row[$c];
            } else {
                $cell = '';
            }
            $out .= $this->renderCell($c, $cell);
            $out .= $this->style->verticalBorderChar;

        }
        if ($rowIndex > 0 && isset($this->rowIndex[$rowIndex])) {
            return $this->renderSeparator() . $out . "\n";
        } else {
            return $out . "\n";
        }
    }

    public function setStyle($style)
    {
        if (is_string($style)) {
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

    public function renderSeparator() {
        $columnNumber = $this->getNumberOfColumns();
        $out = $this->style->rowSeparatorLeftmostCrossChar;
        for ($c = 0 ; $c < $columnNumber ; $c++) {
            $columnWidth = $this->getColumnWidth($c);
            $out .= str_repeat($this->style->rowSeparatorBorderChar, $columnWidth + $this->style->cellPadding * 2);

            if ($c + 1 < $columnNumber) {
                $out .= $this->style->rowSeparatorCrossChar;
            } else {
                $out .= $this->style->rowSeparatorRightmostCrossChar;
            }
        }
        return $out . "\n";
    }

    public function renderHeader() {
        $out = '';

        if ($this->style->drawTableBorder) {
            $out .= $this->renderSeparator();
        }

        $out .= $this->style->verticalBorderChar;
        $columnNumber = $this->getNumberOfColumns();
        for ($c = 0 ; $c < $columnNumber ; $c++) {
            if (isset($this->headers[$c])) {
                $cell = $this->headers[$c];
            } else {
                $cell = '';
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
        for ($c = 0 ; $c < $columnNumber ; $c++) {
            $width += $this->getColumnWidth($c) + $this->style->cellPadding * 2 + 1;
        }
        return $width - 1;
    }

    public function renderCell($cellIndex, $cell)
    {
        $width = $this->getColumnWidth($cellIndex);
        if (function_exists('mb_strlen') && false !== $encoding = mb_detect_encoding($cell)) {
            $width += strlen($cell) - mb_strlen($cell, $encoding);
        }
        if (isset($this->columnCellAttributes[$cellIndex])) {
            return $this->columnCellAttributes[$cellIndex]->renderCell($cell, $width, $this->style);
        }
        return $this->defaultCellAttribute->renderCell($cell, $width, $this->style);
    }

    public function renderFooter()
    {
        if (!is_array($this->footer)) {
            $columnNumber = $this->getNumberOfColumns();
            $out = '';
            $width = $this->getTableInnerWidth();
            $out .= $this->renderSeparator();
            $out .= $this->style->verticalBorderChar 
                . str_repeat($this->style->cellPaddingChar, $this->style->cellPadding)
                . str_pad($this->footer, $width - $this->style->cellPadding * 2) 
                . str_repeat($this->style->cellPaddingChar, $this->style->cellPadding)
                . $this->style->verticalBorderChar . "\n";

            if ($this->style->drawTableBorder) {
                $out .= $this->style->rowSeparatorLeftmostCrossChar . str_repeat($this->style->rowSeparatorBorderChar, $width) 
                    . $this->style->rowSeparatorRightmostCrossChar . "\n";
            }
            return $out;
        }

        $out = '';


        $out .= $this->style->verticalBorderChar;
        $columnNumber = $this->getNumberOfColumns();
        for ($c = 0 ; $c < $columnNumber ; $c++) {
            if (isset($this->footer[$c])) {
                $cell = $this->footer[$c];
            } else {
                $cell = '';
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

    public function render() {
        $out = '';

        if (!empty($this->headers)) {
            $out .= $this->renderHeader();
        } else {
            $out .= $this->renderSeparator();
        }

        foreach($this->rows as $rowIndex => $row) {
            if ($row instanceof RowSeparator) {
                $out .= $this->renderSeparator();
            } else {
                $out .= $this->renderRow($rowIndex, $row);
            }
        }

        // Markdown table does not support footer
        if ($this->style && ! $this->style instanceof MarkdownTableStyle) {
            if (!empty($this->footer)) {
                $out .= $this->renderFooter();
            } else {
                $out .= $this->renderSeparator();
            }
        }
        return $out;
    }

}




