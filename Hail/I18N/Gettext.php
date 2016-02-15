<?php
if (extension_loaded('gettext')) {
	require __DIR__ . '/GettextExt.php';
} else {
	require __DIR__ . '/GettextPhp.php';

	function _($msg)
	{
		return Gettext::gettext($msg);
	}
}

function _e($msg)
{
	echo _($msg);
}

function _n($msg, $msg_plural, $count)
{
	return Gettext::ngettext($msg, $msg_plural, $count);
}

function _d($domain, $msg)
{
	return Gettext::dgettext($domain, $msg);
}

function _dn($domain, $msg, $msg_plural, $count)
{
	return Gettext::dngettext($domain, $msg, $msg_plural, $count);
}
