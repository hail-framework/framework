<?php

namespace Hail\Auth;

use Hail\DITrait;
use Hail\Util\Arrays;

trait GenericTrait
{
    use DITrait;

    /**
     * @var string
     */
    protected $type;

    /**
     * @var array
     */
    protected $attributes;

    /**
     * @var static
     */
    protected $parent;

    public function __construct($attributes)
    {
        $id = $this->getIdName();
        if (\is_array($attributes)) {
            if (!isset($attributes[$id])) {
                throw new \InvalidArgumentException('Field `' . $id . '` not defined');
            }

            $this->attributes = $attributes;

        } else {
            $this->attributes = [
                $id => $attributes,
            ];
        }
    }

    public function getIdName(): string
    {
        return 'id';
    }

    public function getId()
    {
        return $this->attributes[$this->getIdName()];
    }

    public function getType(): string
    {
        if ($this->type === null) {
            $class = static::class;
            $offset = \strrpos($class, '\\') + 1;

            $this->type = \lcfirst(\substr($class, $offset));
        }

        return $this->type;
    }

    public function is($type, $id): bool
    {
        return $this->getType() === $type && $this->getId() === $id;
    }

    /**
     * @param static $target
     *
     * @return bool
     */
    public function equals($target): bool
    {
        if (
            ($this instanceof RoleInterface && $target instanceof RoleInterface) ||
            ($this instanceof SceneInterface && $target instanceof SceneInterface)
        ) {
            return $this->getType() === $target->getType() &&
                $this->getId() === $target->getId();
        }

        return false;
    }

    public function hasAttribute(string $name): bool
    {
        return \array_key_exists($name, $this->attributes[$name]);
    }

    public function getAttribute(string $name)
    {
        return Arrays::get($this->attributes, $name);
    }

    public function setAttribute(string $name, $value)
    {
        Arrays::set($this->attributes, $name, $value);
    }

    public function delAttribute(string $name)
    {
        Arrays::delete($this->attributes, $name);
    }

    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * @param  static $parent
     *
     * @return static
     */
    public function setParent($parent)
    {
        $this->parent = $parent;

        return $this;
    }

    /**
     * @return static|null
     */
    public function getParent()
    {
        return $this->parent;
    }


}