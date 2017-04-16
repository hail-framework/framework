<?php

namespace Hail;

use Hail\Http\Dispatcher as HttpDispatcher;
use Hail\Facade\Output;

/**
 * Class Dispatcher
 * @package Hail
 */
class Dispatcher
{
    /**
     * @var HttpDispatcher
     */
    protected $dispatcher;

    protected $init = false;
    protected $forwards = [];

    /**
     * @var string
     */
    protected $application = '';

    /**
     * @var string
     */
    protected $controller = 'Index';

    /**
     * @var string
     */
    protected $action;

    /**
     * @var array
     */
    protected $params;

    public function __construct(HttpDispatcher $dispatcher)
    {
        $this->dispatcher = $dispatcher;
    }

    public function initialized(): bool
    {
        return $this->init;
    }

    public function current(array $handler)
    {
        if (isset($handler['app'])) {
            $this->application = ucfirst($handler['app']);
        }

        if (isset($handler['controller'])) {
            $this->controller = ucfirst($handler['controller']);
        }

        $this->action = isset($handler['action']) ? lcfirst($handler['action']) : 'index';
        $this->params = $handler['params'] ?? [];

        if (!$this->init) {
            $this->init = true;
        }
    }

    public function getApplication(): string
    {
        return $this->application;
    }

    public function getController(): string
    {
        return $this->controller;
    }

    public function getAction(): string
    {
        return $this->action;
    }

    public function getParam(string $key)
    {
        return $this->params[$key] ?? null;
    }

    public function getParams(): array
    {
        return $this->params;
    }

    public function output($type, $return)
    {
        if ($return === null || $return === false) {
            return;
        }

        if ($return === true) {
            $return = [];
        }

        switch ($type) {
            case 'json':
                if (!is_array($return)) {
                    $return = ['ret' => 0, 'msg' => is_string($return) ? $return : 'OK'];
                } else {
                    if (!isset($return['ret'])) {
                        $return['ret'] = 0;
                        $return['msg'] = '';
                    }
                }

                Output::json()->send($return);
                break;

            case 'text':
                Output::text()->send($return);
                break;

            case 'template':
                $name = $return['_template_'] ??
                    $this->getApplication() . '/' . $this->getController() . '/' . $this->getAction();
                Output::template()->send($name, $return);
                break;
        }
    }

    public function forward($to)
    {
        if ($this->init) {
            $this->forwards[] = [
                'app' => $this->application,
                'controller' => $this->controller,
                'action' => $this->action,
                'params' => $this->params,
            ];
        }

        $this->current($to);
        $this->dispatcher->repeat();

        return null;
    }

    public function error($code, $msg = null)
    {
        return $this->forward([
            'controller' => 'Error',
            'params' => [
                'error' => $code,
                'message' => $msg,
            ],
        ]);
    }
}