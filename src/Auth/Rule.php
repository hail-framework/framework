<?php

namespace Hail\Auth;


class Rule
{
    public const ALLOW = true,
        DENY = false;

    /**
     * @var string
     */
    protected $name;

    /**
     * @var bool
     */
    protected $allow;

    protected $priority = 0;

    /**
     * @var string
     */
    protected $attribute;
    protected $operation;
    protected $value;

    /**
     * @var string|bool
     */
    protected $range = true;

    protected $ruleForBelongTo;


    public function __construct(string $name, bool $allow)
    {
        $this->name = $name;
        $this->allow = $allow;
    }

    public function getName()
    {
        return $this->name;
    }

    /**
     * @param int $priority
     */
    public function setPriority(int $priority): void
    {
        $this->priority = $priority;
    }

    /**
     * @return int
     */
    public function getPriority(): int
    {
        return $this->priority;
    }

    public function setAttribute(string $attribute, string $op = '', $value = null)
    {
        $attribute = \trim($attribute);

        $this->range = true;

        if ($attribute !== '') {
            $parts = \explode(':', $attribute, 2);
            if (!empty($parts[1]) && $parts[1][0] !== ':') {
                [$this->range, $attribute] = $parts;

                if ($this->range === 'this') {
                    $this->range = false;
                }
            }

            $this->attribute = $attribute;
            $this->operation = $op;
            $this->value = $value;
        } else {
            $this->attribute = null;

        }
    }

    public function validate(RoleInterface $role): ?bool
    {
        $range = $this->range;

        if (\is_string($range)) {
            $return = false;
            $roles = $role->getBelongToByType($range);

            if ($roles !== []) {
                $new = $this->forBelongTo();

                foreach ($roles as $v) {
                    if ($this->allow === $new->validate($v)) {
                        $return = true;
                        break;
                    }
                }
            }
        } else {
            $del = false;
            $attr = $this->attribute;
            if (\strpos($attr, '::') !== false) {
                [$type, $field] = \explode('::', $attr, 2);

                $roles = $role->getBelongToByType($type);

                if ($field === 'count') {
                    $del = true;
                    $role->setAttribute($attr, \count($roles));
                }
            }

            $return = self::operation(
                $role, $attr,
                $this->operation, $this->value
            );

            if ($del) {
                $role->delAttribute($attr);
            }

            if ($range && !$return &&
                ($parent = $role->getParent()) !== null
            ) {
                $return = $this->allow === $this->validate($parent);
            }
        }

        return $return ? $this->allow : null;
    }

    public function forBelongTo()
    {
        if ($this->ruleForBelongTo === null) {
            $new = clone $this;
            $new->range = true;

            $this->ruleForBelongTo = $new;
        }

        return $this->ruleForBelongTo;
    }

    protected static function operation(RoleInterface $role, $attribute, $op, $value): bool
    {
        if ($attribute === null) {
            return true;
        }

        $attribute = $role->getAttribute($attribute);

        switch ($op) {
            case '&':
                return (bool) ($attribute & $value);

            case '=':
                return ((string) $attribute) === ((string) $value);

            case '!':
                return ((string) $attribute) !== ((string) $value);

            case '<':
                return $attribute < $value;

            case '>':
                return $attribute > $value;

            case '<=':
                return $attribute <= $value;

            case '>=':
                return $attribute <= $value;

            case '<>':
                [$min, $max] = $value;

                return $attribute > $min && $attribute < $max;

            case '<=>':
                [$min, $max] = $value;

                return $attribute >= $min && $attribute <= $max;

            case '><':
                [$min, $max] = $value;

                return $attribute < $min || $attribute > $max;

            case '>=<':
                [$min, $max] = $value;

                return $attribute <= $min || $attribute >= $max;
        }

        return false;
    }
}