<?php

namespace Hail\Aop;


class Proxy
{
    /**
     * target
     *
     * @var Object
     */
    private $object;

    /**
     * ProxyClient constructor.
     *
     * @param Object $object
     *
     * @throws \InvalidArgumentException
     */
    public function __construct($object)
    {
        if (!\is_object($object)) {
            throw new \InvalidArgumentException('Target must be a object instance');
        }

        $this->object = $object;
    }

    /**
     * @param string $name
     * @param array  $args
     *
     * @return mixed
     */
    public function __call($name, $args)
    {
        if (!\is_callable([$this->object, $name])) {
            throw new \BadMethodCallException("Method '$name' dose not exists");
        }

        $param = new Param($this, $this->object);

        $param->setMethod($name)
            ->setArguments($args)
            ->setJointPoint(Aop::BEFORE);
        Aop::run($param);

        if ($param->isFinished()) {
            return $param->getResult();
        }

        $result = null;

        try {
            $name = $param->getMethod();
            $args = $param->getArguments();

            $result = $this->object->$name(...$args);
        } catch (\Throwable $e) {
            $param->setJointPoint(Aop::EXCEPTION)
                ->setException($e);
            Aop::run($param);
        } finally {
            $param->setJointPoint(Aop::AFTER)
                ->setResult($result)
                ->finished();
            Aop::run($param);
        }

        return $param->getResult();
    }
}