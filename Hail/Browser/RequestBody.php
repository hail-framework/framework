<?php
namespace Hail\Browser;


class RequestBody
{
	/**
	 * Prepares a file for upload. To be used inside the parameters declaration for a request.
	 *
	 * @param string $filename The file path
	 * @param string $mimetype MIME type
	 * @param string $postname the file name
	 *
	 * @return \CURLFile
	 */
	public static function file($filename, $mimetype = null, $postname = null)
	{
		return new \CURLFile($filename, $mimetype, $postname);
	}

	public static function json($data)
	{
		return json_encode($data);
	}

	public static function form($data)
	{
		if (is_string($data)) {
			return $data;
		} else if (is_array($data) || is_object($data) || $data instanceof \Traversable) {
			return http_build_query(
				Request::buildHTTPCurlQuery($data)
			);
		}

		return $data;
	}

	public static function multipart($data, $files = [])
	{
		if (is_object($data)) {
			$data = get_object_vars($data);
		}

		$data = (array) $data;

		foreach ($files as $name => $file) {
			$data[$name] = new \CURLFile($file);
		}

		return $data;
	}
}
