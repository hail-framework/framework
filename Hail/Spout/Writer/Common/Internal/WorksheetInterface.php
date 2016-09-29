<?php

namespace Hail\Spout\Writer\Common\Internal;

/**
 * Interface WorksheetInterface
 *
 * @package Hail\Spout\Writer\Common\Internal
 */
interface WorksheetInterface
{
    /**
     * @return \Hail\Spout\Writer\Common\Sheet The "external" sheet
     */
    public function getExternalSheet();

    /**
     * @return int The index of the last written row
     */
    public function getLastWrittenRowIndex();

    /**
     * Adds data to the worksheet.
     *
     * @param array $dataRow Array containing data to be written.
     *          Example $dataRow = ['data1', 1234, null, '', 'data5'];
     * @param \Hail\Spout\Writer\Style\Style $style Style to be applied to the row. NULL means use default style.
     * @return void
     * @throws \Hail\Spout\Common\Exception\IOException If the data cannot be written
     * @throws \Hail\Spout\Common\Exception\InvalidArgumentException If a cell value's type is not supported
     */
    public function addRow($dataRow, $style);

    /**
     * Closes the worksheet
     *
     * @return void
     */
    public function close();
}
