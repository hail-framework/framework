<?php

namespace Hail\Excel\Common\Helper;

use Hail\Excel\Common\Exception\EncodingConversionException;

/**
 * Class EncodingHelper
 * This class provides helper functions to work with encodings.
 *
 * @package Hail\Excel\Common\Helper
 */
class EncodingHelper
{
	/** Definition of the encodings that can have a BOM */
	const ENCODING_UTF8 = 'UTF-8';
	const ENCODING_UTF16_LE = 'UTF-16LE';
	const ENCODING_UTF16_BE = 'UTF-16BE';
	const ENCODING_UTF32_LE = 'UTF-32LE';
	const ENCODING_UTF32_BE = 'UTF-32BE';

	/** Definition of the BOMs for the different encodings */
	const BOM_UTF8 = "\xEF\xBB\xBF";
	const BOM_UTF16_LE = "\xFF\xFE";
	const BOM_UTF16_BE = "\xFE\xFF";
	const BOM_UTF32_LE = "\xFF\xFE\x00\x00";
	const BOM_UTF32_BE = "\x00\x00\xFE\xFF";


	/** @var array Map representing the encodings supporting BOMs (key) and their associated BOM (value) */
	protected static $supportedEncodingsWithBom = [
		self::ENCODING_UTF8 => self::BOM_UTF8,
		self::ENCODING_UTF16_LE => self::BOM_UTF16_LE,
		self::ENCODING_UTF16_BE => self::BOM_UTF16_BE,
		self::ENCODING_UTF32_LE => self::BOM_UTF32_LE,
		self::ENCODING_UTF32_BE => self::BOM_UTF32_BE,
	];

	public static function detect($bom)
	{
		return array_search($bom, self::$supportedEncodingsWithBom, true);
	}

	/**
	 * Returns the number of bytes to use as offset in order to skip the BOM.
	 *
	 * @param resource $filePointer Pointer to the file to check
	 * @param string $encoding Encoding of the file to check
	 *
	 * @return int Bytes offset to apply to skip the BOM (0 means no BOM)
	 */
	public function getBytesOffsetToSkipBOM($filePointer, $encoding)
	{
		$byteOffsetToSkipBom = 0;

		if ($this->hasBOM($filePointer, $encoding)) {
			$bomUsed = self::$supportedEncodingsWithBom[$encoding];

			// we skip the N first bytes
			$byteOffsetToSkipBom = strlen($bomUsed);
		}

		return $byteOffsetToSkipBom;
	}

	/**
	 * Returns whether the file identified by the given pointer has a BOM.
	 *
	 * @param resource $filePointer Pointer to the file to check
	 * @param string $encoding Encoding of the file to check
	 *
	 * @return bool TRUE if the file has a BOM, FALSE otherwise
	 */
	protected function hasBOM($filePointer, $encoding)
	{
		$hasBOM = false;

		rewind($filePointer);

		if (array_key_exists($encoding, self::$supportedEncodingsWithBom)) {
			$potentialBom = self::$supportedEncodingsWithBom[$encoding];
			$numBytesInBom = strlen($potentialBom);

			$hasBOM = (fgets($filePointer, $numBytesInBom + 1) === $potentialBom);
		}

		return $hasBOM;
	}

	/**
	 * Attempts to convert a non UTF-8 string into UTF-8.
	 *
	 * @param string $string Non UTF-8 string to be converted
	 * @param string $sourceEncoding The encoding used to encode the source string
	 *
	 * @return string The converted, UTF-8 string
	 * @throws \Hail\Excel\Common\Exception\EncodingConversionException If conversion is not supported or if the conversion failed
	 */
	public function attemptConversionToUTF8($string, $sourceEncoding)
	{
		return $this->attemptConversion($string, $sourceEncoding, self::ENCODING_UTF8);
	}

	/**
	 * Attempts to convert a UTF-8 string into the given encoding.
	 *
	 * @param string $string UTF-8 string to be converted
	 * @param string $targetEncoding The encoding the string should be re-encoded into
	 *
	 * @return string The converted string, encoded with the given encoding
	 * @throws \Hail\Excel\Common\Exception\EncodingConversionException If conversion is not supported or if the conversion failed
	 */
	public function attemptConversionFromUTF8($string, $targetEncoding)
	{
		return $this->attemptConversion($string, self::ENCODING_UTF8, $targetEncoding);
	}

	/**
	 * Attempts to convert the given string to the given encoding.
	 * Depending on what is installed on the server, we will try to iconv or mbstring.
	 *
	 * @param string $string string to be converted
	 * @param string $sourceEncoding The encoding used to encode the source string
	 * @param string $targetEncoding The encoding the string should be re-encoded into
	 *
	 * @return string The converted string, encoded with the given encoding
	 * @throws \Hail\Excel\Common\Exception\EncodingConversionException If conversion is not supported or if the conversion failed
	 */
	protected function attemptConversion($string, $sourceEncoding, $targetEncoding)
	{
		// if source and target encodings are the same, it's a no-op
		if ($sourceEncoding === $targetEncoding) {
			return $string;
		}

		$convertedString = null;

		if (\function_exists('\iconv')) {
			$convertedString = \iconv($sourceEncoding, $targetEncoding, $string);
		} else {
			$convertedString = \mb_convert_encoding($string, $sourceEncoding, $targetEncoding);
		}

		if ($convertedString === false) {
			throw new EncodingConversionException("The conversion from $sourceEncoding to $targetEncoding failed.");
		}

		return $convertedString;
	}
}
