<?php

namespace Hail\CLImate\TerminalObject\Router;

use Hail\CLImate\Decorator\Parser\ParserImporter;
use Hail\CLImate\Settings\Manager;
use Hail\CLImate\Settings\SettingsImporter;
use Hail\CLImate\Util\OutputImporter;
use Hail\CLImate\Util\UtilImporter;

class Router
{
    use ParserImporter, SettingsImporter, OutputImporter, UtilImporter;

    /**
     * An instance of the Settings Manager class
     *
     * @var \Hail\CLImate\Settings\Manager $settings;
     */
    protected $settings;

    /**
     * An instance of the Dynamic Router class
     *
     * @var \Hail\CLImate\TerminalObject\Router\DynamicRouter $dynamic;
     */
    protected $dynamic;

    /**
     * An instance of the Basic Router class
     *
     * @var \Hail\CLImate\TerminalObject\Router\BasicRouter $basic;
     */
    protected $basic;

    public function __construct(DynamicRouter $dynamic = null, BasicRouter $basic = null)
    {
        $this->dynamic = $dynamic ?: new DynamicRouter();
        $this->basic   = $basic ?: new BasicRouter();
    }

    /**
     * Check if the name matches an existing terminal object
     *
     * @param string $name
     *
     * @return boolean
     */
    public function exists($name)
    {
        return ($this->basic->exists($name) || $this->dynamic->exists($name));
    }

    /**
     * Execute a terminal object using given arguments
     *
     * @param string $name
     * @param mixed  $arguments
     * @return null|\Hail\CLImate\TerminalObject\Basic\BasicTerminalObjectInterface
     */
    public function execute($name, $arguments)
    {
        $router = $this->getRouter($name);

        $router->output($this->output);

        $reflection = new \ReflectionClass($router->path($name));
        $obj        = $reflection->newInstanceArgs($arguments);

        $obj->parser($this->parser);
        $obj->util($this->util);

        // If the object needs any settings, import them
        foreach ($obj->settings() as $obj_setting) {
            $setting = $this->settings->get($obj_setting);

            if ($setting) {
                $obj->importSetting($setting);
            }
        }

        return $router->execute($obj);
    }

    /**
     * Determine which type of router we are using and return it
     *
     * @param string $name
     *
     * @return \Hail\CLImate\TerminalObject\Router\RouterInterface|null
     */
    protected function getRouter($name)
    {
        if ($this->basic->exists($name)) {
            return $this->basic;
        } elseif ($this->dynamic->exists($name)) {
            return $this->dynamic;
        }
    }

    /**
     * Set the settings property
     *
     * @param  \Hail\CLImate\Settings\Manager $settings
     *
     * @return Router
     */
    public function settings(Manager $settings)
    {
        $this->settings = $settings;

        return $this;
    }

}
