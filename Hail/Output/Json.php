<?php
/**
 * Created by IntelliJ IDEA.
 * User: FlyingHail
 * Date: 2015/12/16 0016
 * Time: 10:42
 */

namespace Hail\Output;


use Hail\DITrait;
use Hail\Tracy\Debugger;
use Hail\Utils\Json as Js;

/**
 * Class Json
 * @package Hail\Output
 */
class Json
{
	use DITrait;

	public function send($content) {
		$contentType = (Debugger::isEnabled() && !$this->request->isAjax()) ? 'text/html' : 'application/json';
		$this->response->setContentType($contentType, 'utf-8');
		$this->response->setExpiration(false);
		echo Js::encode($content);
	}
}