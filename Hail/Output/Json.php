<?php
/**
 * Created by IntelliJ IDEA.
 * User: Hao
 * Date: 2015/12/16 0016
 * Time: 10:42
 */

namespace Hail\Output;


use Hail\DITrait;
use Hail\Tracy\Debugger;

class Json
{
	use DITrait;

	public function send($content) {
		$contentType = Debugger::isEnabled() ? 'text/html' : 'application/json';
		$this->response->setContentType($contentType, 'utf-8');
		$this->response->setExpiration(false);
		echo json_encode($content);
	}
}