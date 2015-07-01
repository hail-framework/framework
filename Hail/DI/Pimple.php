<?php
if (class_exists('Pimple\\Container', false)) {
	require __DIR__ . '/PimpleExt.php';
} else {
	require __DIR__ . '/PimplePhp.php';
}