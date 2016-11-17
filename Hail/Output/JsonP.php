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
use Hail\Exception\BadRequest;
use Hail\Utils\Json as Js;

/**
 * Class Jsonp
 * @package Hail\Output
 */
class Jsonp extends Json
{
	public function send($content, $callback = null) {
		if ($callback === null) {
			$callback = Request::getParam('callback');
			if (empty($callback)) {
				throw new BadRequest("callback doesn't defined.");
			}
		}

		Response::setContentType('text/javascript', 'utf-8');
		Response::setExpiration(false);

		echo $callback . '(' . Js::encode($content) . ')';
	}
}