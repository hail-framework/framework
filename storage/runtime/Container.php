<?php
class Container extends Hail\Container\Container
{
	protected static $entryPoints = [
		'config' => 'HAILPARAM__config',
		Hail\Config::class => 'HAIL__Hail__Config',
		'acl' => 'HAILPARAM__acl',
		Hail\Acl::class => 'HAIL__Hail__Acl',
		'alias' => 'HAILPARAM__alias',
		Hail\AliasLoader::class => 'HAIL__Hail__AliasLoader',
		'app' => 'HAILPARAM__app',
		Hail\Application::class => 'HAIL__Hail__Application',
		'arrays' => 'HAILPARAM__arrays',
		Hail\Util\Arrays::class => 'HAIL__Hail__Util__Arrays',
		'browser' => 'HAILPARAM__browser',
		Hail\Browser::class => 'HAIL__Hail__Browser',
	];

	public function get($name)
	{
		if (isset($this->active[$name])) {
			return $this->values[$name];
		}

		if (isset(static::$entryPoints[$name])) {
			return $this->{static::$entryPoints[$name]}();
		}

		return parent::get($name);
	}

	protected function HAILPARAM__config() {
		$this->active['config'] = true;
		return $this->values['config'] = $this->get(Hail\Config::class);
	}

	protected function HAIL__Hail__Config() {
		$this->active[Hail\Config::class] = true;
		return $this->values[Hail\Config::class] = new Hail\Config();
	}

	protected function HAILPARAM__acl() {
		$this->active['acl'] = true;
		return $this->values['acl'] = $this->get(Hail\Acl::class);
	}

	protected function HAIL__Hail__Acl() {
		$this->active[Hail\Acl::class] = true;
		return $this->values[Hail\Acl::class] = new Hail\Acl();
	}

	protected function HAILPARAM__alias() {
		$this->active['alias'] = true;
		return $this->values['alias'] = $this->get(Hail\AliasLoader::class);
	}

	protected function HAIL__Hail__AliasLoader() {
		$this->active[Hail\AliasLoader::class] = true;
		return $this->values[Hail\AliasLoader::class] = new Hail\AliasLoader($this->get('config')->get('alias'));
	}

	protected function HAILPARAM__app() {
		$this->active['app'] = true;
		return $this->values['app'] = $this->get(Hail\Application::class);
	}

	protected function HAIL__Hail__Application() {
		$this->active[Hail\Application::class] = true;
		return $this->values[Hail\Application::class] = new Hail\Application();
	}

	protected function HAILPARAM__arrays() {
		$this->active['arrays'] = true;
		return $this->values['arrays'] = $this->get(Hail\Util\Arrays::class);
	}

	protected function HAIL__Hail__Util__Arrays() {
		$this->active[Hail\Util\Arrays::class] = true;
		return $this->values[Hail\Util\Arrays::class] = new Hail\Util\Arrays();
	}

	protected function HAILPARAM__browser() {
		$this->active['browser'] = true;
		return $this->values['browser'] = $this->get(Hail\Browser::class);
	}

	protected function HAIL__Hail__Browser() {
		$this->active[Hail\Browser::class] = true;
		return $this->values[Hail\Browser::class] = new Hail\Browser();
	}}