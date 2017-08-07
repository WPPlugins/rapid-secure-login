<?php

namespace Httpful;

/**************************************************************************************
Library:           HTTPFUL
Description:       Expansion of the httpful PHAR library 
				   for systems that do not support phar
**************************************************************************************/
 
require plugin_dir_path( __FILE__) . 'Http.php';
require plugin_dir_path( __FILE__) . 'Httpful.php';
require plugin_dir_path( __FILE__) . 'Mime.php';
require plugin_dir_path( __FILE__) . 'Proxy.php';
require plugin_dir_path( __FILE__) . 'Request.php';
require plugin_dir_path( __FILE__) . 'Response.php';

require plugin_dir_path( __FILE__) . 'Exception/ConnectionErrorException.php';

require plugin_dir_path( __FILE__) . 'Handlers/MimeHandlerAdapter.php';
require plugin_dir_path( __FILE__) . 'Handlers/CsvHandler.php';
require plugin_dir_path( __FILE__) . 'Handlers/FormHandler.php';
require plugin_dir_path( __FILE__) . 'Handlers/JsonHandler.php';
require plugin_dir_path( __FILE__) . 'Handlers/XHtmlHandler.php';
require plugin_dir_path( __FILE__) . 'Handlers/XmlHandler.php';

require plugin_dir_path( __FILE__) . 'Response/Headers.php';


/**
 * Bootstrap class that facilitates autoloading.  A naive
 * PSR-0 autoloader.
 *
 * @author Nate Good <me@nategood.com>
 */
class Bootstrap
{

    const DIR_GLUE = DIRECTORY_SEPARATOR;
    const NS_GLUE = '\\';

    public static $registered = false;

    /**
     * Register the autoloader and any other setup needed
     */
    public static function init()
    {
        //CPE UN-PHAR spl_autoload_register(array('\Httpful\Bootstrap', 'autoload'));
        self::registerHandlers();
    }

    /**
     * The autoload magic (PSR-0 style)
     *
     * @param string $classname
     */
    public static function autoload($classname)
    {
        self::_autoload(dirname(dirname(__FILE__)), $classname);
    }

    /**
     * Register the autoloader and any other setup needed
     */
    public static function pharInit()
    {
        //CPE UN-PHAR spl_autoload_register(array('\Httpful\Bootstrap', 'pharAutoload'));
        self::registerHandlers();
    }

    /**
     * Phar specific autoloader
     *
     * @param string $classname
     */
    public static function pharAutoload($classname)
    {
        //CPE UN-PHAR self::_autoload('phar://httpful.phar', $classname);
    }

    /**
     * @param string $base
     * @param string $classname
     */
    private static function _autoload($base, $classname)
    {
        $parts      = explode(self::NS_GLUE, $classname);
        $path       = $base . self::DIR_GLUE . implode(self::DIR_GLUE, $parts) . '.php';

        if (file_exists($path)) {
            require_once($path);
        }
    }
    /**
     * Register default mime handlers.  Is idempotent.
     */
    public static function registerHandlers()
    {
        if (self::$registered === true) {
            return;
        }

        // @todo check a conf file to load from that instead of
        // hardcoding into the library?
        $handlers = array(
            \Httpful\Mime::JSON => new \Httpful\Handlers\JsonHandler(),
            \Httpful\Mime::XML  => new \Httpful\Handlers\XmlHandler(),
            \Httpful\Mime::FORM => new \Httpful\Handlers\FormHandler(),
            \Httpful\Mime::CSV  => new \Httpful\Handlers\CsvHandler(),
        );

        foreach ($handlers as $mime => $handler) {
            // Don't overwrite if the handler has already been registered
            if (Httpful::hasParserRegistered($mime))
                continue;
            Httpful::register($mime, $handler);
        }

        self::$registered = true;
    }
}
