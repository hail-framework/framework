<?php

namespace Hail\Excel\Reader\XLSX;

/**
 * Class ReaderOptions
 * This class is used to customize the reader's behavior
 *
 * @package Hail\Excelt\Reader\XLSX
 */
class ReaderOptions extends \Hail\Excel\Reader\Common\ReaderOptions
{
	/** @var string|null Temporary folder where the temporary files will be created */
	protected $tempFolder = null;

	/**
	 * @return string|null Temporary folder where the temporary files will be created
	 */
	public function getTempFolder()
	{
		return $this->tempFolder;
	}

	/**
	 * @param string|null $tempFolder Temporary folder where the temporary files will be created
	 * @return ReaderOptions
	 */
	public function setTempFolder($tempFolder)
	{
		$this->tempFolder = $tempFolder;
		return $this;
	}
}

