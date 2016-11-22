<?php
namespace Hail\Output;


use Hail\Facades\Response;
use Hail\Http\Response as Code;

class Redirect
{
	/**
	 * @param string $url
	 * @param int $code
	 */
	public function send($url, $code = Code::S302_FOUND)
	{
		$url = (string) $url;
		$code = (int) $code;
		Response::redirect($url, $code);
	}

}