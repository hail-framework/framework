<?php

namespace Hail\Aop;


class Param
{
    /**
     * @var array[][][]
     */
    private static $params;

    /**
     * @var bool
     */
    private $finished = false;

    /**
     * @var string
     */
    private $className;

    /**
     * @var Object
     */
    private $object;

    /**
     * @var Proxy
     */
    private $aop;

    /**
     * @var string
     */
    private $method;

    /**
     * @var array
     */
    private $arguments;

    /**
     * @var mixed
     */
    private $result;

    /**
     * @var \Throwable
     */
    private $exception;

    /**
     * @var string
     */
    private $point;

    /**
     * Param constructor.
     *
     * @param Proxy  $aop
     * @param Object $object
     */
    public function __construct($aop, $object)
    {
        $this->aop = $aop;
        $this->object = $object;
        $this->className = \get_class($object);
    }

    public function __clone()
    {
        $this->method =
        $this->arguments =
        $this->result =
        $this->exception =
        $this->point = null;

        $this->finished = false;
    }

    /**
     * @return bool
     */
    public function isFinished(): bool
    {
        return $this->finished === true;
    }

    /**
     * @return $this
     */
    public function finished()
    {
        $this->finished = true;

        return $this;
    }

    /**
     * @return Proxy
     */
    public function getAOP(): Proxy
    {
        return $this->aop;
    }

    /**
     * @return Object
     */
    public function getObject()
    {
        return $this->object;
    }

    /**
     * @return string
     */
    public function getMethod(): string
    {
        return $this->method;
    }

    /**
     * @param $method
     *
     * @return $this
     */
    public function setMethod($method)
    {
        $this->method = $method;

        return $this;
    }

    /**
     * @return array
     */
    public function getArguments(): array
    {
        return $this->arguments;
    }

    /**
     * @param array $args
     *
     * @return $this
     */
    public function setArguments(array $args)
    {
        $className = $this->className;
        $method = $this->method;

        $arguments = [];

        if (isset(self::$params[$className][$method])) {
            foreach (self::$params[$className][$method] as ['index' => $index, 'default' => $default]) {
                $arguments[$index] = \array_key_exists($index, $args) ? $args[$index] : $default;
            }
        } else {
            $reflectionMethod = new \ReflectionMethod($this->object, $method);

            $parameters = $reflectionMethod->getParameters();
            foreach ($parameters as $index => $parameter) {
                $name = $parameter->getName();
                $default = $parameter->isDefaultValueAvailable() ? $parameter->getDefaultValue() : null;

                self::$params[$className][$method][$name] = [
                    'index' => $index,
                    'default' => $default,
                ];

                $arguments[$index] = \array_key_exists($index, $args) ? $args[$index] : $default;
            }
        }

        $this->arguments = $arguments;

        return $this;
    }

    /**
     * @param string $name
     *
     * @return mixed|null
     */
    public function getArgumentByName(string $name)
    {
        $param = self::$params[$this->className][$this->method];

        if (!isset($param[$name])) {
            return null;
        }

        return $this->arguments[$param[$name]['index']];
    }

    /**
     * @param int $index
     *
     * @return mixed|null
     */
    public function getArgumentByIndex(int $index)
    {
        return $this->arguments[$index] ?? null;
    }

    /**
     * @return string
     */
    public function getJointPoint(): string
    {
        return $this->point;
    }

    /**
     * @param string $point
     *
     * @return $this
     */
    public function setJointPoint(string $point)
    {
        $this->point = $point;

        return $this;
    }

    /**
     * @return \Throwable|null
     */
    public function getException(): ?\Throwable
    {
        return $this->exception;
    }

    /**
     * @param \Throwable $exception
     *
     * @return $this
     */
    public function setException(\Throwable $exception)
    {
        $this->exception = $exception;

        return $this;
    }

    /**
     * @return bool
     */
    public function hasException(): bool
    {
        return $this->exception instanceof \Throwable;
    }

    /**
     * @return mixed
     */
    public function getResult()
    {
        return $this->result;
    }

    /**
     * @param $result
     *
     * @return $this
     */
    public function setResult($result)
    {
        $this->result = $result;

        return $this;
    }
}