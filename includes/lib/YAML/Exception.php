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

/**
 * Exception class for exceptions thrown by Horde_Yaml
 *
 * @category Horde
 * @package  Horde_Yaml
 */
class Horde_Yaml_Exception extends Exception
{

    public function __construct($message = null, $code_or_lasterror = 0)
    {
        if (is_array($code_or_lasterror)) {
            if ($message) {
                $message .= $code_or_lasterror['message'];
            } else {
                $message = $code_or_lasterror['message'];
            }

            $this->file = $code_or_lasterror['file'];
            $this->line = $code_or_lasterror['line'];
            $code = $code_or_lasterror['type'];
        } else {
            $code = $code_or_lasterror;
        }

        parent::__construct($message, $code);
    }

}
