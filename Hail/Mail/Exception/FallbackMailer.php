<?php
/**
 * Created by IntelliJ IDEA.
 * User: Hao
 * Date: 2016/10/8 0008
 * Time: 15:48
 */

namespace Hail\Mail\Exception;


class FallbackMailer extends Send
{
	/** @var Send[] */
	public $failures;
}