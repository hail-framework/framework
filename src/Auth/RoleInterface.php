<?php

namespace Hail\Auth;

use Hail\Auth\Exception\EntryException;

interface RoleInterface
{
    public function getId();

    public function getType(): string;

    public function is($type, $id): bool;

    /**
     * @param RoleInterface $target
     *
     * @return bool
     */
    public function equals($target): bool;

    public function getAttributes(): array;

    public function getAttribute(string $name);

    public function setAttribute(string $name, $value);

    public function delAttribute(string $name);

    /**
     * @param RoleInterface $parent
     *
     * @return RoleInterface
     */
    public function setParent($parent);

    /**
     * @return RoleInterface|null
     */
    public function getParent();

    public function addBelongTo(RoleInterface $role);

    public function getBelongTo(string $name, string $id): ?RoleInterface;

    /**
     * @param string $name
     *
     * @return RoleInterface[]
     */
    public function getBelongToByType(string $name): array;

    public function delBelongTo(string $name, string $id);

    public function delBelongToByType(string $name);

    public function belongTo($name);

    /**
     * @param SceneInterface $scene
     *
     * @return RoleInterface
     * @throws EntryException
     */
    public function entry(SceneInterface $scene): RoleInterface;

    public function getScene(): ?SceneInterface;

    public function setScene(SceneInterface $scene = null);
}