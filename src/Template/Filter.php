<?php

namespace Hail\Template;

final class Filter
{
    /**
     * @var callable[]
     */
    private $filters;

    /**
     * @var mixed
     */
    private $result;

    /**
     * Filter constructor.
     *
     * @param callable[] $filters
     * @param mixed $value
     *
     * @throws \InvalidArgumentException
     */
    public function __construct($filters, $value)
    {
        foreach ($filters as $name => $filter) {
            if (!\is_callable($filter)) {
                throw new \InvalidArgumentException('Filter must be callable');
            }

            $this->filters[$name] = $filter;
        }

        $this->result = $value;
    }

    /**
     * @param string $name
     * @param array  $arguments
     *
     * @return $this
     * @throws \BadMethodCallException
     */
    public function __call($name, $arguments)
    {
        if (!isset($this->filters[$name])) {
            throw new \BadMethodCallException('Filter not found: ' . $name);
        }

        $this->result = $this->filters[$name]($this->result, ...$arguments);

        return $this;
    }

    /**
     * @return mixed
     */
    public function getResult()
    {
        return $this->result;
    }
}