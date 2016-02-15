<?php
/**
 * Created by IntelliJ IDEA.
 * User: FlyingHail
 * Date: 2015/12/16 0016
 * Time: 11:42
 */

namespace Hail\Output;


use Hail\DITrait;
use Hail\Http\Response;

class Redirect
{
	use DITrait;

	/**
	 * @param string $url
	 * @param int $code
	 */
	public function send($url, $code = Response::S302_FOUND)
	{
		$url = (string) $url;
		$code = (int) $code;
		$this->response->redirect($url, $code);
	}

}