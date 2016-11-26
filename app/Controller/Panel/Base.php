<?php
/**
 * Created by IntelliJ IDEA.
 * User: FlyingHail
 * Date: 2016/11/26 0026
 * Time: 20:45
 */

namespace app\Controller\Panel;


use Hail\Controller;

abstract class Base extends Controller
{
	public function error($no, $msg = null)
	{
		return $this->dispatcher->forward([
			'controller' => 'Error',
			'params' => [
				'error' => $no,
				'message' => $msg,
			],
		]);
	}
}