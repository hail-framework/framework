<?php
use Hail\Facades\I18N;

if (extension_loaded('gettext')) {
	require __DIR__ . '/GettextExt.php';
} else {
	require __DIR__ . '/GettextPhp.php';

	function _($msg)
	{
		return I18N::gettext($msg);
	}
}

function _e($msg)
{
	echo _($msg);
}

function _n($msg, $msg_plural, $count)
{
	return I18N::ngettext($msg, $msg_plural, $count);
}

function _d($domain, $msg)
{
	return I18N::dgettext($domain, $msg);
}

function _dn($domain, $msg, $msg_plural, $count)
{
	return I18N::dngettext($domain, $msg, $msg_plural, $count);
}
