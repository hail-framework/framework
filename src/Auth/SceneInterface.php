<?php

namespace Hail\Auth;

use Hail\Auth\Exception\EntryException;

interface SceneInterface
{
    public function getId();

    public function getType(): string;

    public function is($type, $id): bool;

    /**
     * @param SceneInterface $target
     *
     * @return bool
     */
    public function equals($target): bool;

    public function getAttributes(): array;

    public function getAttribute(string $name);

    public function setAttribute(string $name, $value);

    public function delAttribute(string $name);

    /**
     * @param SceneInterface $parent
     *
     * @return SceneInterface
     */
    public function setParent($parent);

    /**
     * @return SceneInterface|null
     */
    public function getParent();

    public function in(RoleInterface $role): void;

    public function out(RoleInterface $role): void;

    public function rules();

    public function addRule(Rule $rule);

    public function getRule(string $name): iterable;

    public function validate(RoleInterface $role, string $name): ?bool;

}