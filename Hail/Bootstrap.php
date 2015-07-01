<?php
namespace Hail;

/**
 * Class Bootstrap
 * @package Hail
 */
class Bootstrap
{
    /**
     * Define constants
     */
    public static function constant()
    {
        // Now timestamp
        define('NOW', isset($_SERVER['REQUEST_TIME']) ? $_SERVER['REQUEST_TIME'] : time());

        // Is this an AJAX request?
        define('AJAX_REQUEST', isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');

        // The current TLD address, scheme, and port
        define('DOMAIN', (isset($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) === 'on' ? 'https' : 'http') . '://'
            . $_SERVER['HTTP_HOST'] . (($p = $_SERVER['SERVER_PORT']) != 80 AND $p != 443 ? ":$p" : ''));

        define('METHOD', isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'GET');

        // The current site path
        define('PATH', parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

        // Embedded cache engine: 'auto', 'apcu', 'apc', 'xcache', 'yac', 'pcache', 'wincache', 'none'
        define('EMBEDDED_CACHE_ENGINE', 'auto');
	    define('EMBEDDED_CACHE_CHECK_DELAY', 5);

        // Config Setting
        define('CONFIG_ENABLE_YACONF', false);
        define('CONFIG_PATH', SYSTEM_PATH . 'config/');

	    // Language path
	    define('LANG_PATH', SYSTEM_PATH . 'lang/');
    }

    /**
     * Dependency Injection Container init
     *
     * @return DI\Pimple
     */
    public static function di()
    {
	    require HAIL_PATH . 'Cache/EmbeddedTrait.php';
	    require HAIL_PATH . 'DI/Pimple.php';

        return new DI\Pimple([
            'EmbeddedCache' => function($c) {
	            require HAIL_PATH . 'Cache/Embedded.php';

                return new Cache\Embedded(
                    EMBEDDED_CACHE_ENGINE
                );
            },

            'Config' => function($c) {
	            if (defined('CONFIG_ENABLE_YACONF') && CONFIG_ENABLE_YACONF &&
		            extension_loaded('yaconf')
	            ) {
		            require HAIL_PATH . 'Config/Yaconf.php';
		            return new Config\Yaconf();
	            } else {
		            require HAIL_PATH . 'Config/Php.php';
		            return new Config\Php($c);
	            }
            },

            'Loader' => function($c) {
	            require HAIL_PATH . 'Loader/PSR4.php';
                return new Loader\PSR4($c);
            },

	        // After herer, no need to add require
            'Alias' => function($c) {
                return new Loader\Alias(
                    $c['Config']->get('env.alias')
                );
            },

	        'Router' => function ($c) {
		        return new Route\Tree();
	        },

	        'Gettext' => function($c) {
		        return new I18N\Gettext();
	        }
        ]);
    }

    /**
     * Register autoload
     *
     * @param DI\Pimple $di
     */
    public static function loader($di)
    {
        $di['Loader']->addPrefixes(
            $di['Config']->get('env.autoload')
        );
        $di['Loader']->register();
        $di['Alias']->register();

        \DI::swap($di);
    }

	/**
	 * Set Locale
	 */
	public static function locale()
	{
		date_default_timezone_set(
			\Config::get('env.timezone')
		);

		\Gettext::init(LANG_PATH,
			\Config::get('env.lang_file'),
			\Config::get('env.locale')
		);
	}
}
