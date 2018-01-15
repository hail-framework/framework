<?php

namespace Hail\Auth;


use Hail\Auth\Exception\EntryException;

class Manager
{
    protected const ROLE = 'Role',
        SCENE = 'Scene';

    /**
     * @var string
     */
    protected $namespace = '\App\Auth';

    /**
     * @var array
     */
    protected $config;

    /**
     * @var array
     */
    protected $object;

    /**
     * @var SceneInterface
     */
    protected $currentScene;

    /**
     * @var RoleInterface
     */
    protected $currentRole;

    public function __construct(array $config = [])
    {
        if (isset($config['namespace'])) {
            $this->namespace = '\\' . \trim($config['namespace'], '\\');
        }

        if (!isset($config['default'])) {
            throw new \InvalidArgumentException('Default not defined');
        }

        $this->setDefaultConfig($config['default']);

        if (!empty($config['roles'])) {
            foreach ($config['roles'] as $name => $conf) {
                $this->setRoleConfig($name, $conf);
            }
        }

        if (!empty($config['scenes'])) {
            foreach ($config['scenes'] as $name => $conf) {
                $this->setSceneConfig($name, $conf);
            }
        }
    }

    public function validate($name): bool
    {
        $role = $this->getCurrentRole();
        $scene = $this->getCurrentScene();

        return $scene->validate($role, $name) ?? $this->getDefaultPermission();
    }

    public function createRule(
        string $name,
        bool $allow,
        int $priority = 0,
        string $attribute = '',
        string $op = null,
        $value = null
    ) {
        $rule = new Rule($name, $allow);
        $rule->setPriority($priority);
        $rule->setAttribute($attribute, $op, $value);

        return $rule;
    }

    public function getRole(string $name, $id): RoleInterface
    {
        return $this->object[self::ROLE][$name][$id] ?? $this->getObject(self::ROLE, $name, $id);
    }

    public function getScene(string $name, $id): SceneInterface
    {
        return $this->object[self::SCENE][$name][$id] ?? $this->getObject(self::SCENE, $name, $id);
    }

    protected function getObject(string $type, string $name, $id)
    {
        if (isset($this->object[$type][$name][$id])) {
            return $this->object[$type][$name][$id];
        }

        $name = \lcfirst($name);

        $config = $this->getConfig($type, $name);
        /** @var RoleInterface|SceneInterface $object */
        $object = new $config['class']($id);

        if (isset($config['parentAttr'])) {
            $nid = $object->getAttribute($config['parentAttr']);

            $object->setParent(
                $this->getObject($type, $name, $nid)
            );
        }

        switch (true) {
            case $object instanceof RoleInterface:
                if (isset($config['belongTo'])) {
                    foreach ((array) $config['belongTo'] as $t) {
                        $object->belongTo($t);
                    }
                }
                break;

            case $object instanceof SceneInterface:
                $object->rules();
                break;
        }

        return $this->object[$type][$name][$id] = $object;
    }

    /**
     * @param string $name
     * @param        $id
     *
     * @return SceneInterface
     * @throws EntryException
     */
    public function toScene(string $name, $id): SceneInterface
    {
        $role = $this->getCurrentRole();
        $to = $this->getScene($name, $id);

        $newRole = $role->entry($to);

        if (!$role->equals($newRole)) {
            $this->currentRole = $newRole;
        }

        return $to;
    }

    public function getCurrentScene(): SceneInterface
    {
        $scene = $this->currentRole->getScene();
        if ($scene !== null) {
            [
                'name' => $name,
                'id' => $id,
            ] = $this->getDefaultSceneConfig();

            $scene = $this->toScene($name, $id);
        }

        return $scene;
    }

    public function getCurrentRole(): RoleInterface
    {
        if ($this->currentRole === null) {
            [
                'name' => $name,
                'id' => $id,
            ] = $this->getDefaultSceneConfig();

            $this->currentRole = $this->getRole($name, $id);
        }

        return $this->currentRole;
    }

    public function getRoleConfig($name)
    {
        if ($name instanceof RoleInterface) {
            $name = $name->getType();
        }

        return $this->getConfig(self::ROLE, $name);
    }

    public function getSceneConfig($name)
    {
        if ($name instanceof SceneInterface) {
            $name = $name->getType();
        }

        return $this->getConfig(self::SCENE, $name);
    }

    protected function getConfig(string $type, string $name)
    {
        if (!isset($this->config[$type][$name])) {
            throw new \LogicException("Config not defined: $type($name)");
        }

        return $this->config[$type][$name];
    }

    public function setRoleConfig(string $name, array $config)
    {
        return $this->setConfig(self::ROLE, $name, $config);
    }

    public function setSceneConfig(string $name, array $config)
    {
        return $this->setConfig(self::SCENE, $name, $config);
    }

    protected function setConfig(string $type, string $name, array $config)
    {
        if (isset($config['class'])) {
            $class = $config['class'];
        } elseif ($this->namespace === null) {
            throw new \InvalidArgumentException($type . ' class not defined');
        } else {
            $class = $this->namespace . '\\' . $type . '\\' . \ucfirst($name);
        }

        if (!\is_a($class, __NAMESPACE__ . "\\{$type}Interface", true)) {
            throw new \InvalidArgumentException($type . ' class invalid: ' . $class);
        }

        $config['class'] = $class;

        $name = \lcfirst($name);
        $this->config[$type][$name] = $config;

        return $this;
    }

    public function getDefaultPermission(): bool
    {
        return $this->getConfig('default', 'permission');
    }

    public function getDefaultRoleConfig()
    {
        return $this->getConfig('default', self::ROLE);
    }

    public function getDefaultSceneConfig()
    {
        return $this->getConfig('default', self::SCENE);
    }

    public function setDefaultConfig(array $config)
    {
        if (!isset(
            $config['role']['name'],
            $config['role']['id'],
            $config['scene']['name'],
            $config['scene']['id'],
            $config['permission']
        )) {
            throw new \InvalidArgumentException('Default config invalid');
        }

        if (\is_string($config['permission'])) {
            $config['permission'] = \strtolower($config['permission']) === 'allow';
        } else {
            $config['permission'] = (bool) $config['permission'];
        }

        $this->config['default'] = [
            self::ROLE => $config['role'],
            self::SCENE => $config['scene'],
            'permission' => $config['permission'],
        ];

        return $this;
    }

    public function clearExpired()
    {
        foreach ($this->object[self::ROLE] as $name => &$array) {
            foreach ($array as $id => $role) {
                /** @var RoleInterface $role */
                if ($role->isExpire()) {
                    $role->getScene()->out($role);

                    unset($array[$id]);
                }
            }
        }
    }
}