<?php
/**
 * Created by IntelliJ IDEA.
 * User: FlyingHail
 * Date: 2016/2/14 0014
 * Time: 22:58
 */

namespace App\Controller\Panel;

use Hail\Controller;

class Error extends Controller
{
	public function indexAction()
	{
		$error = $this->dispatcher->getParam('error');
		$message = $this->dispatcher->getParam('message');

		$errorMessage = $this->config->get('error.' . $error);
		if ($errorMessage) {
			$message = $message ? sprintf($errorMessage, $message) : $errorMessage;
		}

		return [
			'ret' => $error,
			'msg' => $message,
		];
	}
}