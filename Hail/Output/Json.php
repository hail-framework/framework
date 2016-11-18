<?php
/**
 * Created by IntelliJ IDEA.
 * User: FlyingHail
 * Date: 2015/12/16 0016
 * Time: 10:42
 */

namespace Hail\Output;


use Hail\Facades\{
	Request,
	Response
};
use Hail\Utils\Json as Js;

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