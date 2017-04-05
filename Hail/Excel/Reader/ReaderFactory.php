<?php

namespace Hail\Excel\Reader;

use Hail\Excel\Common\Exception\UnsupportedTypeException;
use Hail\Excel\Common\Type;

/**
 * Class ReaderFactory
 * This factory is used to create readers, based on the type of the file to be read.
 * It supports CSV and XLSX formats.
 *
 * @package Hail\Excel\Reader
 */
class ReaderFactory
{
    /**
     * This creates an instance of the appropriate reader, given the type of the file to be read
     *
     * @api
     * @param  string $readerType Type of the reader to instantiate
     * @return ReaderInterface
     * @throws \Hail\Excel\Common\Exception\UnsupportedTypeException
     */
    public static function create($readerType)
    {
        $reader = null;

        switch ($readerType) {
            case Type::CSV:
                $reader = new CSV\Reader();
                break;
            case Type::XLSX:
                $reader = new XLSX\Reader();
                break;
            case Type::ODS:
                $reader = new ODS\Reader();
                break;
            default:
                throw new UnsupportedTypeException('No readers supporting the given type: ' . $readerType);
        }

        return $reader;
    }

	public static function open($file, $tempPath = STORAGE_PATH . 'excel')
	{
		$type = strtolower(substr($file, strrpos($file, '.') + 1));
		$reader = self::create($type);
		if (method_exists($reader, 'setTempFolder')) {
			$reader->setTempFolder($tempPath);
		}
		$reader->open($file);
		return $reader;
	}
}
