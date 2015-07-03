<?php

/**
 * This file is part of the Nette Framework (http://nette.org)
 * Copyright (c) 2004 David Grudl (http://davidgrudl.com)
 */

namespace Hail\Http;


/**
 * Provides access to individual files that have been uploaded by a client.
 *
 * @author     David Grudl
 *
 * @property-read string $name
 * @property-read string $sanitizedName
 * @property-read string|NULL $contentType
 * @property-read int $size
 * @property-read string $temporaryFile
 * @property-read int $error
 * @property-read bool $ok
 * @property-read bool $image
 * @property-read array|NULL $imageSize
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
		foreach (array('name', 'type', 'size', 'tmp_name', 'error') as $key) {
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
	 * @return string
	 */
	public function getName()
	{
		return $this->name;
	}


	/**
	 * Returns the sanitized file name.
	 * @return string
	 */
	public function getSanitizedName()
	{
		return trim(Nette\Utils\Strings::webalize($this->name, '.', FALSE), '.-');
	}


	/**
	 * Returns the MIME content type of an uploaded file.
	 * @return string|NULL
	 */
	public function getContentType()
	{
		if ($this->isOk() && $this->type === NULL) {
			$this->type = finfo_file(finfo_open(FILEINFO_MIME_TYPE), $this->tmpName);
		}
		return $this->type;
	}


	/**
	 * Returns the size of an uploaded file.
	 * @return int
	 */
	public function getSize()
	{
		return $this->size;
	}


	/**
	 * Returns the path to an uploaded file.
	 * @return string
	 */
	public function getTemporaryFile()
	{
		return $this->tmpName;
	}


	/**
	 * Returns the path to an uploaded file.
	 * @return string
	 */
	public function __toString()
	{
		return (string) $this->tmpName;
	}


	/**
	 * Returns the error code. {@link http://php.net/manual/en/features.file-upload.errors.php}
	 * @return int
	 */
	public function getError()
	{
		return $this->error;
	}


	/**
	 * Is there any error?
	 * @return bool
	 */
	public function isOk()
	{
		return $this->error === UPLOAD_ERR_OK;
	}


	/**
	 * Move uploaded file to new location.
	 * @param  string
	 * @return self
	 * @throws \RuntimeException
	 */
	public function move($dest)
	{
		@mkdir(dirname($dest), 0777, TRUE); // @ - dir may already exist
		@unlink($dest); // @ - file may not exists
		if (!call_user_func(is_uploaded_file($this->tmpName) ? 'move_uploaded_file' : 'rename', $this->tmpName, $dest)) {
			throw new \RuntimeException("Unable to move uploaded file '$this->tmpName' to '$dest'.");
		}
		chmod($dest, 0666);
		$this->tmpName = $dest;
		return $this;
	}


	/**
	 * Is uploaded file GIF, PNG or JPEG?
	 * @return bool
	 */
	public function isImage()
	{
		return in_array($this->getContentType(), array('image/gif', 'image/png', 'image/jpeg'), TRUE);
	}


	/**
	 * Returns the image.
	 * @return Nette\Utils\Image
	 * @throws Nette\Utils\ImageException
	 */
	public function toImage()
	{
		return Nette\Utils\Image::fromFile($this->tmpName);
	}


	/**
	 * Returns the dimensions of an uploaded image as array.
	 * @return array|NULL
	 */
	public function getImageSize()
	{
		return $this->isOk() ? @getimagesize($this->tmpName) : NULL; // @ - files smaller than 12 bytes causes read error
	}


	/**
	 * Get file contents.
	 * @return string|NULL
	 */
	public function getContents()
	{
		// future implementation can try to work around safe_mode and open_basedir limitations
		return $this->isOk() ? file_get_contents($this->tmpName) : NULL;
	}

	/**
	 * Converts to web safe characters [a-z0-9-] text.
	 * @param  string  UTF-8 encoding
	 * @param  string  allowed characters
	 * @param  bool
	 * @return string
	 */
	public static function webalize($s, $charlist = NULL, $lower = TRUE)
	{
		$s = self::toAscii($s);
		if ($lower) {
			$s = strtolower($s);
		}
		$s = preg_replace('#[^a-z0-9' . preg_quote($charlist, '#') . ']+#i', '-', $s);
		$s = trim($s, '-');
		return $s;
	}

	/**
	 * Converts to ASCII.
	 * @param  string  UTF-8 encoding
	 * @return string  ASCII
	 */
	public static function toAscii($s)
	{
		$s = preg_replace('#[^\x09\x0A\x0D\x20-\x7E\xA0-\x{2FF}\x{370}-\x{10FFFF}]#u', '', $s);
		$s = strtr($s, '`\'"^~?', "\x01\x02\x03\x04\x05\x06");
		$s = str_replace(
			array("\xE2\x80\x9E", "\xE2\x80\x9C", "\xE2\x80\x9D", "\xE2\x80\x9A", "\xE2\x80\x98", "\xE2\x80\x99", "\xC2\xB0"),
			array("\x03", "\x03", "\x03", "\x02", "\x02", "\x02", "\x04"), $s
		);
		if (class_exists('Transliterator', false) && $transliterator = \Transliterator::create('Any-Latin; Latin-ASCII')) {
			$s = $transliterator->transliterate($s);
		}
		if (ICONV_IMPL === 'glibc') {
			$s = str_replace(
				array("\xC2\xBB", "\xC2\xAB", "\xE2\x80\xA6", "\xE2\x84\xA2", "\xC2\xA9", "\xC2\xAE"),
				array('>>', '<<', '...', 'TM', '(c)', '(R)'), $s
			);
			$s = @iconv('UTF-8', 'WINDOWS-1250//TRANSLIT//IGNORE', $s); // intentionally @
			$s = strtr($s, "\xa5\xa3\xbc\x8c\xa7\x8a\xaa\x8d\x8f\x8e\xaf\xb9\xb3\xbe\x9c\x9a\xba\x9d\x9f\x9e"
				. "\xbf\xc0\xc1\xc2\xc3\xc4\xc5\xc6\xc7\xc8\xc9\xca\xcb\xcc\xcd\xce\xcf\xd0\xd1\xd2\xd3"
				. "\xd4\xd5\xd6\xd7\xd8\xd9\xda\xdb\xdc\xdd\xde\xdf\xe0\xe1\xe2\xe3\xe4\xe5\xe6\xe7\xe8"
				. "\xe9\xea\xeb\xec\xed\xee\xef\xf0\xf1\xf2\xf3\xf4\xf5\xf6\xf8\xf9\xfa\xfb\xfc\xfd\xfe"
				. "\x96\xa0\x8b\x97\x9b\xa6\xad\xb7",
				"ALLSSSSTZZZallssstzzzRAAAALCCCEEEEIIDDNNOOOOxRUUUUYTsraaaalccceeeeiiddnnooooruuuuyt- <->|-.");
			$s = preg_replace('#[^\x00-\x7F]++#', '', $s);
		} else {
			$s = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s); // intentionally @
		}
		$s = str_replace(array('`', "'", '"', '^', '~', '?'), '', $s);
		return strtr($s, "\x01\x02\x03\x04\x05\x06", '`\'"^~?');
	}
}
