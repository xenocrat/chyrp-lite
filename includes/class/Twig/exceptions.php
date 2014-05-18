<?php
/**
 * Twig::Exceptions
 * ~~~~~~~~~~~~~~~~
 *
 * This module implements the Twig exceptions.
 *
 * :copyright: 2008 by Armin Ronacher.
 * :license: GNU GPL.
 */



/**
 * Baseclass for all exceptions twig may throw.  This is useful for
 * instance-checks to silence all twig errors for example.
 */
class Twig_Exception extends Exception {}


/**
 * This exception is raised when the template engine is unable to
 * parse or lex a template.  Because the getFile() method and similar
 * methods are final we can't override them here but provide the real
 * filename and line number as public property.
 */
class Twig_SyntaxError extends Twig_Exception
{
    public $lineno;
    public $filename;

    public function __construct($message, $lineno, $filename=null)
    {
        parent::__construct($message);
        $this->lineno = $lineno;
        $this->filename = $filename;
    }
}


/**
 * Thrown when Twig encounters an exception at runtime in the Twig
 * core.
 */
class Twig_RuntimeError extends Twig_Exception
{
    public function __construct($message)
    {
        parent::__construct($message);
    }
}


/**
 * Raised if the loader is unable to find a template.
 */
class Twig_TemplateNotFound extends Twig_Exception
{
    public $name;

    public function __construct($name)
    {
        parent::__construct('Template not found: ' . $name);
        $this->name = $name;
    }
}
