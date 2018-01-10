<?php

namespace Hail\Debugger\Bar;

use Hail\Facade\{
    Request, Router
};
use Hail\Debugger\Dumper;

/**
 * Routing debugger for Debug Bar.
 */
class RoutePanel implements PanelInterface
{
    /** @var array */
    private $result = [];

    /**
     * Renders tab.
     *
     * @return string
     */
    public function getTab()
    {
        \ob_start();
        $title = $this->getRouteResult()['title'];

        require __DIR__ . '/templates/route.tab.phtml';

        return \ob_get_clean();
    }

    /**
     * Renders panel.
     *
     * @return string
     */
    public function getPanel()
    {
        \ob_start();
        $router = $this->getRouteResult();
        $url = (string) Request::uri();
        $sorted = [
            'matched', 'url', 'error', 'allowed', 'route', 'params', 'handler',
        ];

        require __DIR__ . '/templates/route.panel.phtml';

        return \ob_get_clean();
    }

    private function getRouteResult()
    {
        if ($this->result === null) {
            $result = Router::getResult();

            if (isset($result['error'])) {
                if ($result['error']['code'] === 404) {
                    $result['matched'] = 'no';
                    $result['title'] = 'Not Found';
                } else {
                    $result['matched'] = 'may';
                    $result['title'] = 'Not Allowed';
                }
            } else {
                $result['matched'] = 'yes';
                $result['title'] = 'Matched';
            }

            $this->result = $result;
        }

        return $this->result;
    }

    private function dump($value, $br = false)
    {
        return \is_string($value) ?
            \htmlspecialchars($value, ENT_IGNORE, 'UTF-8') . ($br ? '<br />' : '') :
            Dumper::toHtml($value, [Dumper::COLLAPSE => true, Dumper::LIVE => true]);
    }
}
