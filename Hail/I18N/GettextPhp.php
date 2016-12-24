<?php
/**
 * @from https://github.com/Ashrey/PHP-Gettext
 * Copyright (c) 2014 David Soria Parra Modified by Alberto Berroteran and FlyingHail
 */

namespace Hail\I18N;

use Hail\Util\OptimizeTrait;

class Gettext
{
	use OptimizeTrait;

	/**
	 * First magic word in the MO header
	 */
	const MAGIC1 = (int) 0xde120495;

	/**
	 * First magic word in the MO header
	 */
	const MAGIC2 = (int) 0x950412de;

	protected $dir;
	protected $domain;
	protected $locale;
	protected $translationTable = [];
	protected $parsed = [];

	/**
	 * Initialize a new gettext class
	 *
	 * @param string $directory
	 * @param string $domain
	 * @param string $locale
	 */
	public function init($directory, $domain, $locale)
	{
		$this->dir = $directory;
		$this->domain = $domain;
		$this->locale = $locale;

		$this->parse($locale, $domain);
	}

	/**
	 * Parse the MO file
	 *
	 * @param string $locale
	 * @param string $domain
	 *
	 * @return void
	 */
	private function parse($locale, $domain)
	{
		$this->parsed[$locale][$domain] = true;
		$mo = "{$this->dir}/{$locale}/LC_MESSAGES/{$domain}.mo";

		$key = "$locale/$domain";
		$array = $this->optimizeGet($key, $mo);
		if ($array !== false) {
			$this->translationTable[$locale][$domain] = $array;

			return;
		}

		$this->translationTable[$locale][$domain] = [];
		if (file_exists($mo) && filesize($mo) >= 4 * 7) {
			$this->parseFile($mo, $locale, $domain);
		}

		$this->optimizeSet($key, $this->translationTable[$locale][$domain], $mo);
	}


	/**
	 * Parse the MO file header and returns the table
	 * offsets as described in the file header.
	 *
	 * If an exception occured, null is returned. This is intentionally
	 * as we need to get close to ext/gettexts beahvior.
	 *
	 * @param resource $fp The open file handler to the MO file
	 *
	 * @return array An array of offset
	 */
	private function parseHeader($fp)
	{
		$data = fread($fp, 8);
		$header = unpack('lmagic/lrevision', $data);
		if (self::MAGIC1 !== $header['magic'] && self::MAGIC2 !== $header['magic']) {
			return null;
		}
		if (0 !== $header['revision']) {
			return null;
		}
		$data = fread($fp, 4 * 5);
		$offsets = unpack('lnum_strings/lorig_offset/ltrans_offset/lhash_size/lhash_offset', $data);

		return $offsets;
	}

	/**
	 * Parse and reutnrs the string offsets in a a table. Two table can be found in
	 * a mo file. The table with the translations and the table with the original
	 * strings. Both contain offsets to the strings in the file.
	 *
	 * If an exception occured, null is returned. This is intentionally
	 * as we need to get close to ext/gettexts beahvior.
	 *
	 * @param resource $fp The open file handler to the MO file
	 * @param int $offset The offset to the table that should be parsed
	 * @param int $num The number of strings to parse1
	 *
	 * @return array Array of offsets
	 */
	private function parseOffsetTable($fp, $offset, $num)
	{
		if (fseek($fp, $offset, SEEK_SET) < 0) {
			return [];
		}
		$table = [];
		for ($i = 0; $i < $num; $i++) {
			$data = fread($fp, 8);
			$table[] = unpack('lsize/loffset', $data);
		}

		return $table;
	}

	/**
	 * Parse a string as referenced by an table. Returns an
	 * array with the actual string.
	 *
	 * @param resource $fp The open file handler to the MO fie
	 * @param array $entry The entry as parsed by parseOffsetTable()
	 *
	 * @return string Parsed string
	 */
	private function parseEntry($fp, $entry)
	{
		if (fseek($fp, $entry['offset'], SEEK_SET) < 0) {
			return null;
		}
		if ($entry['size'] > 0) {
			return fread($fp, $entry['size']);
		}

		return '';
	}

	/**
	 * generate a file to cache
	 *
	 * @param string $file .mo file
	 * @param string $locale locale
	 * @param string $domain domain
	 */
	private function parseFile($file, $locale, $domain)
	{
		$fp = fopen($file, 'rb');
		$offsets = $this->parseHeader($fp);
		if (null === $offsets || filesize($file) < 4 * ($offsets['num_strings'] + 7)) {
			fclose($fp);

			return;
		}

		$table = $this->parseOffsetTable($fp, $offsets['trans_offset'], $offsets['num_strings']);
		if (null === $table) {
			fclose($fp);

			return;
		}
		$this->generateTables($fp, $locale, $domain, $table, $offsets);
		fclose($fp);
	}

	/**
	 * Generate the tables
	 *
	 * @param resource $fp
	 * @param string $locale
	 * @param string $domain
	 * @param array $table
	 * @param array $offsets
	 */
	private function generateTables($fp, $locale, $domain, Array $table, Array $offsets)
	{
		$transTable = [];
		foreach ($table as $idx => $entry) {
			$transTable[$idx] = $this->parseEntry($fp, $entry);
		}
		$table = $this->parseOffsetTable($fp, $offsets['orig_offset'], $offsets['num_strings']);
		$chr = chr(0);
		foreach ($table as $idx => $entry) {
			$entry = $this->parseEntry($fp, $entry);
			$formes = explode($chr, $entry);
			$translation = explode($chr, $transTable[$idx]);
			foreach ($formes as $form) {
				$this->translationTable[$locale][$domain][$form] = $translation;
			}
		}
	}

	/**
	 * Return a translated string
	 *
	 * If the translation is not found, the original passed message
	 * will be returned.
	 *
	 * @param string $msg
	 *
	 * @return string Translated message
	 */
	public function gettext($msg)
	{
		if (isset($this->translationTable[$this->locale][$this->domain][$msg])) {
			return $this->translationTable[$this->locale][$this->domain][$msg][0];
		}

		return $msg;
	}

	/**
	 * Overrides the domain for a single lookup
	 *
	 * If the translation is not found, the original passed message
	 * will be returned.
	 *
	 * @param string $domain The domain to search in
	 * @param string $msg The message to search for
	 *
	 * @return string Translated string
	 */
	public function dgettext($domain, $msg)
	{
		if (!isset($this->parsed[$this->locale][$domain])) {
			$this->parse($this->locale, $domain);
		}
		if (isset($this->translationTable[$this->locale][$domain][$msg])) {
			return $this->translationTable[$this->locale][$domain][$msg][0];
		}

		return $msg;
	}

	/**
	 * Return a translated string in it's plural form
	 *
	 * Returns the given $count (e.g second, third,...) plural form of the
	 * given string. If the id is not found and $num == 1 $msg is returned,
	 * otherwise $msg_plural
	 *
	 * @param string $msg The message to search for
	 * @param string $msg_plural A fallback plural form
	 * @param int $count Which plural form
	 *
	 * @return string Translated string
	 */
	public function ngettext($msg, $msg_plural, int $count)
	{
		$msg = (string) $msg;
		if (isset($this->translationTable[$this->locale][$this->domain][$msg])) {
			$translation = $this->translationTable[$this->locale][$this->domain][$msg];
			/* the gettext api expect an unsigned int, so we just fake 'cast' */
			if ($count <= 0) {
				$count = count($translation);
			} elseif (($min = count($translation)) < $count) {
				$count = $min;
			}

			return $translation[$count - 1];
		}
		/* not found, handle count */
		if (1 === $count) {
			return $msg;
		} else {
			return $msg_plural;
		}
	}

	/**
	 * Override the current domain for a single plural message lookup
	 *
	 * Returns the given $count (e.g second, third,...) plural form of the
	 * given string. If the id is not found and $num == 1 $msg is returned,
	 * otherwise $msg_plural
	 *
	 * @param string $domain The domain to search in
	 * @param string $msg The message to search for
	 * @param string $msg_plural A fallback plural form
	 * @param int $count Which plural form
	 *
	 * @return string Translated string
	 */
	public function dngettext($domain, $msg, $msg_plural, int $count)
	{
		if (!isset($this->parsed[$this->locale][$domain])) {
			$this->parse($this->locale, $domain);
		}

		$msg = (string) $msg;
		if (isset($this->translationTable[$this->locale][$domain][$msg])) {
			$translation = $this->translationTable[$this->locale][$domain][$msg];
			/* the gettext api expect an unsigned int, so we just fake 'cast' */
			if ($count <= 0) {
				$count = count($translation);
			} elseif (($min = count($translation)) < $count) {
				$count = $min;
			}

			return $translation[$count - 1];
		}
		/* not found, handle count */
		if (1 === $count) {
			return $msg;
		} else {
			return $msg_plural;
		}
	}
}