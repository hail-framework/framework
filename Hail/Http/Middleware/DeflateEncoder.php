<?php

namespace Hail\Http\Middleware;

class DeflateEncoder extends GzipEncoder
{
	public function __construct()
	{
		$this->encoding = 'deflate';
	}

	/**
	 * {@inheritdoc}
	 */
	protected function encode($content)
	{
		return gzdeflate($content);
	}
}