<?php

namespace Hail\Excel\Writer;

use Hail\Excel\Common\Exception\UnsupportedTypeException;
use Hail\Excel\Common\Type;

/**
 * Class WriterFactory
 * This factory is used to create writers, based on the type of the file to be read.
 * It supports CSV, XLSX and ODS formats.
 *
 * @package Hail\Excel\Writer
 */
class WriterFactory
{
    /**
     * This creates an instance of the appropriate writer, given the type of the file to be read
     *
     * @api
     * @param  string $writerType Type of the writer to instantiate
     * @return WriterInterface
     * @throws \Hail\Excel\Common\Exception\UnsupportedTypeException
     */
    public static function create($writerType)
    {
        $writer = null;

        switch ($writerType) {
            case Type::CSV:
                $writer = new CSV\Writer();
                break;
            case Type::XLSX:
                $writer = new XLSX\Writer();
                break;
            case Type::ODS:
                $writer = new ODS\Writer();
                break;
            default:
                throw new UnsupportedTypeException('No writers supporting the given type: ' . $writerType);
        }

        return $writer;
    }

    private static function auto($file, $tempPath)
    {
	    $type = strtolower(substr($file, strrpos($file, '.') + 1));
	    $writer = self::create($type);
	    if (method_exists($writer, 'setTempFolder')) {
		    $writer->setTempFolder($tempPath);
	    }

	    return $writer;
    }

	public static function browser($file, $tempPath = TEMP_PATH . 'excel')
	{
		$writer = self::auto($file, $tempPath);
		$writer->openToBrowser($file);
		return $writer;
	}

	public static function file($file, $tempPath = TEMP_PATH . 'excel')
	{
		$writer = self::auto($file, $tempPath);
		$writer->openToFile($file);
		return $writer;
	}
}
