<?php

namespace Hail\Http\Middleware;

class DeflateEncoder extends GzipEncoder
{
	/**
	 * @var string
	 */
	protected $encoding = 'deflate';

	/**
	 * {@inheritdoc}
	 */
	protected function encode($content)
	{
		return \gzdeflate($content);
	}
}