<?php

namespace Hail\Debugger\Bar;

use Hail\Debugger\Profiler;

class ProfilerPanel implements PanelInterface
{
    /**
     * @inheritdoc
     */
    public function getTab()
    {
        \ob_start();
        $title = Profiler::count();
        $title .= $title > 1 ? 'profiles' : 'profile';

        require __DIR__ . '/templates/profiler.tab.phtml';

        return \ob_get_clean();
    }

    /**
     * @inheritdoc
     */
    public function getPanel()
    {
        \ob_start();
        require __DIR__ . '/templates/profiler.panel.phtml';

        return \ob_get_clean();
    }
}