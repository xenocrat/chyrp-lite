<?php
/**
 * Horde YAML package
 *
 * This package is heavily inspired by the Spyc PHP YAML
 * implementation (http://spyc.sourceforge.net/), and portions are
 * copyright 2005-2006 Chris Wanstrath.
 *
 * @author   Chris Wanstrath (chris@ozmm.org)
 * @author   Chuck Hagenbuch (chuck@horde.org)
 * @author   Mike Naberezny (mike@maintainable.com)
 * @license  http://opensource.org/licenses/bsd-license.php BSD
 * @category Horde
 * @package  Horde_Yaml
 */

require "YAML/Dumper.php";
require "YAML/Exception.php";
require "YAML/Loader.php";
require "YAML/Node.php";

/**
 * Horde YAML parser.
 *
 * This class can be used to read a YAML file and convert its contents
 * into a PHP array. The native PHP parser supports a limited
 * subsection of the YAML spec, but if the syck extension is present,
 * that will be used for parsing.
 *
 * @category Horde
 * @package  Horde_Yaml
 */
class YAML
{
    /**
     * Callback used for alternate YAML loader, typically exported
     * by a faster PHP extension.  This function's first argument
     * must accept a string with YAML content.
     *
     * @var callback
     */
    public static $loadfunc = 'syck_load';

    /**
     * Whitelist of classes that can be instantiated automatically
     * when loading YAML docs that include serialized PHP objects.
     *
     * @var array
     */
    public static $allowedClasses = array('ArrayObject');

    /**
     * Load a string containing YAML and parse it into a PHP array.
     * Returns an empty array on failure.
     *
     * @param  string  $yaml   String containing YAML
     * @return array           PHP array representation of YAML content
     */
    public static function load($yaml)
    {
        if (@is_file($yaml))
            return self::loadFile($yaml);

        if (!is_string($yaml) || !strlen($yaml)) {
            $msg = 'YAML to parse must be a string and cannot be empty.';
            throw new InvalidArgumentException($msg);
        }

        if (is_callable(self::$loadfunc)) {
            $array = call_user_func(self::$loadfunc, $yaml);
            return is_array($array) ? $array : array();
        }

        if (strpos($yaml, "\r") !== false) {
            $yaml = str_replace(array("\r\n", "\r"), array("\n", "\n"), $yaml);
        }
        $lines = explode("\n", $yaml);
        $loader = new Horde_Yaml_Loader;

        while (list(,$line) = each($lines)) {
            $loader->parse($line);
        }

        return $loader->toArray();
    }

    /**
     * Load a file containing YAML and parse it into a PHP array.
     *
     * If the file cannot be opened, an exception is thrown.  If the
     * file is read but parsing fails, an empty array is returned.
     *
     * @param  string  $filename     Filename to load
     * @return array                 PHP array representation of YAML content
     * @throws IllegalArgumentException  If $filename is invalid
     * @throws Horde_Yaml_Exception  If the file cannot be opened.
     */
    public static function loadFile($filename)
    {
        if (!is_string($filename) || !strlen($filename)) {
            $msg = 'Filename must be a string and cannot be empty';
            throw new InvalidArgumentException($msg);
        }

        $stream = @fopen($filename, 'rb');
        if (!$stream) {
            throw new Horde_Yaml_Exception('Failed to open file: ', error_get_last());
        }

        return self::loadStream($stream);
    }

    /**
     * Load YAML from a PHP stream resource.
     *
     * @param  resource  $stream     PHP stream resource
     * @return array                 PHP array representation of YAML content
     */
    public static function loadStream($stream)
    {
        if (! is_resource($stream) || get_resource_type($stream) != 'stream') {
            throw new InvalidArgumentException('Stream must be a stream resource');
        }

        if (is_callable(self::$loadfunc)) {
            $array = call_user_func(self::$loadfunc, stream_get_contents($stream));
            return is_array($array) ? $array : array();
        }

        $loader = new Horde_Yaml_Loader;
        while (!feof($stream)) {
            $loader->parse(stream_get_line($stream, 100000, "\n"));
        }

        return $loader->toArray();
    }

    /**
     * Dump a PHP array to YAML.
     *
     * The dump method, when supplied with an array, will do its best
     * to convert the array into friendly YAML.
     *
     * @param  array|Traversable  $array     PHP array or traversable object
     * @param  integer            $options   Options to pass to dumper
     * @return string                        YAML representation of $value
     */
    public static function dump($value, $options = array())
    {
        $dumper = new Horde_Yaml_Dumper;
        return $dumper->dump($value, $options);
    }

}
