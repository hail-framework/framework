<?php

namespace Hail\Util;


trait SafeStorageTrait
{
    /**
     * @var SafeStorage
     */
    protected $safeStorage;

    public function safeSet($key, $value)
    {
        if ($this->safeStorage === null) {
            $this->safeStorage = new SafeStorage();
        }

        $this->safeStorage->set($key, $value);
    }

    public function safeGet($key)
    {
        if ($this->safeStorage === null) {
            return null;
        }

        return $this->safeStorage->get($key);
    }

    public function setPassword($password)
    {
        $this->safeSet('password', $password);

        return $this;
    }

    public function getPassword()
    {
        return $this->safeGet('password');
    }
}