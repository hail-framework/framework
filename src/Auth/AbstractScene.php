<?php

namespace Hail\Auth;


abstract class AbstractScene implements SceneInterface
{
    use GenericTrait;

    protected $rules = [];

    public function in(RoleInterface $role): void
    {
        $role->setScene($this);
    }

    public function out(RoleInterface $role): void
    {
        $role->setScene();
    }

    public function addRule(Rule $rule)
    {
        $name = $rule->getName();

        if (!isset($this->rules[$name])) {
            $this->rules[$name] = new \SplPriorityQueue();
        }

        $this->rules[$name]->insert($rule, $rule->getPriority());

        return $this;
    }

    /**
     * @param string $name
     *
     * @return iterable
     */
    public function getRule(string $name): iterable
    {
        return $this->rules[$name] ?? [];
    }

    public function validate(RoleInterface $role, string $name): ?bool
    {
        $found = null;
        /** @var Rule $rule */
        foreach ($this->getRule($name) as $rule) {
            $valid = $rule->validate($role);
            if (\is_bool($valid)) {
                return $valid;
            }
        }

        if ($found === null) {
            if (($pos = \strrpos($name, '.')) !== false) {
                $found = $this->validate($role, \substr($name, 0, $pos));
            } else {
                $found = $this->validate($role, '*');
            }
        }

        if ($found === null && ($parent = $this->getParent()) !== null) {
            $found = $parent->validate($role, $name);
        }

        return $found;
    }
}