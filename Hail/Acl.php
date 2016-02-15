<?php
/**
 * Created by IntelliJ IDEA.
 * User: FlyingHail
 * Date: 2016/2/13 0013
 * Time: 23:06
 */

namespace Hail;


class Acl
{
	use DITrait;

	public function crypt($password, $salt = null)
	{
		$password = hash_hmac('sha256', $password, $salt, true);
		return base64_encode($password);
	}

}