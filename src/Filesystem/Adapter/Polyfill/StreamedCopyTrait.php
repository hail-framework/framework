<?php

namespace Hail\Filesystem\Adapter\Polyfill;

trait StreamedCopyTrait
{
	/**
	 * Copy a file.
	 *
	 * @param string $path
	 * @param string $newpath
	 *
	 * @return bool
	 */
	public function copy($path, $newpath)
	{
		$response = $this->readStream($path);

		if ($response === false || !\is_resource($response['stream'])) {
			return false;
		}

		$result = $this->writeStream($newpath, $response['stream'], []);

		if ($result !== false && \is_resource($response['stream'])) {
			\fclose($response['stream']);
		}

		return $result !== false;
	}

	// Required abstract method

	/**
	 * @param  string $path
	 *
	 * @return resource
	 */
	abstract public function readStream($path);

	/**
	 * @param  string   $path
	 * @param  resource $resource
	 * @param  array    $config
	 *
	 * @return resource
	 */
	abstract public function writeStream($path, $resource, array $config);
}
