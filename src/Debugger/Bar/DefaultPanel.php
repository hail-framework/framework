<?php

/**
 * This file is part of the Tracy (http://tracy.nette.org)
 * Copyright (c) 2004 David Grudl (http://davidgrudl.com) Modified by FlyingHail <flyinghail@msn.com>
 */

namespace Hail\Debugger\Bar;


/**
 * IBarPanel implementation helper.
 *
 * @internal
 */
class DefaultPanel implements PanelInterface
{
    private $id;
    public $data;
    public $cpuUsage;


    public function __construct(string $id)
    {
        $this->id = $id;
    }


    /**
     * Renders HTML code for custom tab.
     *
     * @return string
     */
    public function getTab(): string
    {
        \ob_start();
        $data = $this->data;
        require __DIR__ . "/templates/{$this->id}.tab.phtml";

        return \ob_get_clean();
    }


    /**
     * Renders HTML code for custom panel.
     *
     * @return string
     */
    public function getPanel(): string
    {
        \ob_start();
        if (\is_file(__DIR__ . "/templates/{$this->id}.panel.phtml")) {
            $data = $this->data;
            require __DIR__ . "/templates/{$this->id}.panel.phtml";
        }

        return \ob_get_clean();
    }

}
