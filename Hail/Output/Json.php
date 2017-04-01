<?php
namespace Hail\Output;


use Hail\Facade\{
	Request,
	Response
};
use Hail\Facade\Json as Js;

/**
 * Class Json
 * @package Hail\Output
 */
class Json
{
	public function send($content) {
		$contentType = !Request::expectsJson() ? 'text/html' : 'application/json';
		Response::setContentType($contentType, 'utf-8');
		Response::setExpiration(false);
		echo Js::encode($content);
	}
}