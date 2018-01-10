<?php

namespace Hail;

/**
 * Class Action
 *
 * @package Hail
 * @author  Hao Feng <flyinghail@msn.com>
 */
abstract class Action
{
    use DITrait;

	abstract public function __invoke();
}