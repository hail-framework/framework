<?php

/**
 * This file is part of the Nette Framework (http://nette.org)
 * Copyright (c) 2004 David Grudl (http://davidgrudl.com) Modified by FlyingHail <flyinghail@msn.com>
 */

namespace Hail\Http;


/**
 * Provides access to individual files that have been uploaded by a client.
 *
 * @property-read string $name
 * @property-read string $sanitizedName
 * @property-read string|NULL $contentType
 * @property-read int $size
 * @property-read string $temporaryFile
 * @property-read int $error
 * @property-read bool $ok
 * @property-read string|NULL $contents
 */
class FileUpload
{
	/** @var string */
	private $name;

	/** @var string */
	private $type;

	/** @var string */
	private $size;

	/** @var string */
	private $tmpName;

	/** @var int */
	private $error;


	public function __construct($value)
	{
		foreach (['name', 'type', 'size', 'tmp_name', 'error'] as $key) {
			if (!isset($value[$key]) || !is_scalar($value[$key])) {
				$this->error = UPLOAD_ERR_NO_FILE;

				return; // or throw exception?
			}
		}
		$this->name = $value['name'];
		$this->size = $value['size'];
		$this->tmpName = $value['tmp_name'];
		$this->error = $value['error'];
	}

	public function __get($key)
	{
		$fun = 'get' . ucfirst($key);

		return $this->$fun();
	}

	/**
	 * Returns the file name.
	 *
	 * @return string
	 */
	public function getName()
	{
		return $this->name;
	}


	/**
	 * Returns the sanitized file name.
	 *
	 * @return string
	 */
	public function getSanitizedName()
	{
		return trim(Helpers::webalize($this->name, '.', false), '.-');
	}


	/**
	 * Returns the MIME content type of an uploaded file.
	 *
	 * @return string|NULL
	 */
	public function getContentType()
	{
		if ($this->type === null && $this->isOk()) {
			$this->type = finfo_file(finfo_open(FILEINFO_MIME_TYPE), $this->tmpName);
		}

		return $this->type;
	}


	/**
	 * Returns the size of an uploaded file.
	 *
	 * @return int
	 */
	public function getSize()
	{
		return $this->size;
	}


	/**
	 * Returns the path to an uploaded file.
	 *
	 * @return string
	 */
	public function getTemporaryFile()
	{
		return $this->tmpName;
	}

	public function getExtension()
	{
		return strrchr($this->name, '.');
	}


	/**
	 * Returns the path to an uploaded file.
	 *
	 * @return string
	 */
	public function __toString()
	{
		return (string) $this->tmpName;
	}


	/**
	 * Returns the error code. {@link http://php.net/manual/en/features.file-upload.errors.php}
	 *
	 * @return int
	 */
	public function getError()
	{
		return $this->error;
	}

	/**
	 * @return bool
	 */
	public function hasFile()
	{
		return $this->error !== UPLOAD_ERR_NO_FILE;
	}

	/**
	 * Is there any error?
	 *
	 * @return bool
	 */
	public function isOk()
	{
		return $this->error === UPLOAD_ERR_OK;
	}


	/**
	 * Move uploaded file to new location.
	 *
	 * @param  string
	 *
	 * @return self
	 * @throws \RuntimeException
	 */
	public function move($dest)
	{
		if (file_exists($dest)) {
			unlink($dest);
		} else {
			$dir = dirname($dest);
			if (!is_dir($dir) && (!@mkdir($dir, 0777, true) && !is_dir($dir))) {
				throw new \RuntimeException("Unable to create dir '$dir'.");
			}
		}

		$moveFun = is_uploaded_file($this->tmpName) ? 'move_uploaded_file' : 'rename';
		if (!@$moveFun($this->tmpName, $dest)) {
			throw new \RuntimeException("Unable to move uploaded file '$this->tmpName' to '$dest'.");
		}

		@chmod($dest, 0666);
		$this->tmpName = $dest;

		return $this;
	}


	/**
	 * Is uploaded file GIF, PNG or JPEG?
	 *
	 * @return bool
	 */
	public function isImage()
	{
		return in_array($this->getContentType(), ['image/gif', 'image/png', 'image/jpeg'], true);
	}


//	/**
//	 * Returns the image.
//	 * @return Hail\Util\Image
//	 */
//	public function toImage()
//	{
//		return Hail\Util\Image::fromFile($this->tmpName);
//	}


	/**
	 * Returns the dimensions of an uploaded image as array.
	 *
	 * @return array|NULL
	 */
	public function getImageSize()
	{
		return $this->isOk() ? @getimagesize($this->tmpName) : null; // @ - files smaller than 12 bytes causes read error
	}


	/**
	 * Get file contents.
	 *
	 * @return string|NULL
	 */
	public function getContents()
	{
		// future implementation can try to work around safe_mode and open_basedir limitations
		return $this->isOk() ? file_get_contents($this->tmpName) : null;
	}
}
