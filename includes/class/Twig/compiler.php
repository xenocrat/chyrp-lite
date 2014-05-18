<?php
/**
 * Twig::Compiler
 * ~~~~~~~~~~~~~~
 *
 * This module implements the Twig compiler.
 *
 * :copyright: 2008 by Armin Ronacher.
 * :license: BSD.
 */


// mark the compiler as being included.  This use used by the public
// `twig_load_compiler` function that loads the compiler system.
define('TWIG_COMPILER_INCLUDED', true);
require TWIG_BASE . '/lexer.php';
require TWIG_BASE . '/parser.php';
require TWIG_BASE . '/ast.php';


function twig_compile($node, $fp=null)
{
    if (!is_null($fp))
        $compiler = new Twig_FileCompiler($fp);
    else
        $compiler = new Twig_StringCompiler();
    $node->compile($compiler);
    if (is_null($fp))
        return $compiler->getCode();
}


class Twig_Compiler
{
    private $last_lineno;

    public function __construct()
    {
        $this->last_lineno = NULL;
    }

    public function format()
    {
        $arguments = func_get_args();
        $this->raw(call_user_func_array('sprintf', $arguments));
    }

    public function string($value)
    {
        $this->format('"%s"', addcslashes($value, "\t\""));
    }

    public function repr($value)
    {
        if (is_int($value) || is_float($value))
            $this->raw($value);
        else if (is_null($value))
            $this->raw('NULL');
        else if (is_bool($value))
            $this->raw($value ? 'true' : 'false');
        else if (is_array($value)) {
            $this->raw('array(');
            $i = 0;
            foreach ($value as $key => $value) {
                if ($i++)
                    $this->raw(', ');
                $this->repr($key);
                $this->raw(' => ');
                $this->repr($value);
            }
            $this->raw(')');
        }
        else
            $this->string($value);
    }

    public function pushContext()
    {
        $this->raw('$context[\'::parent\'] = $parent = $context;'. "\n");
    }

    public function popContext()
    {
        $this->raw('$context = $context[\'::parent\'];'. "\n");
    }

    public function addDebugInfo($node)
    {
        if ($node->lineno != $this->last_lineno) {
            $this->last_lineno = $node->lineno;
            $this->raw("/* LINE:$node->lineno */\n");
        }
    }
}


class Twig_FileCompiler extends Twig_Compiler
{
    private $fp;

    public function __construct($fp)
    {
        parent::__construct();
        $this->fp = $fp;
    }

    public function raw($string)
    {
        fwrite($this->fp, $string);
    }
}


class Twig_StringCompiler extends Twig_Compiler
{
    private $source;

    public function __construct()
    {
        parent::__construct();
        $this->source = '';
    }

    public function raw($string)
    {
        $this->source .= $string;
    }

    public function getCode()
    {
        return $this->source;
    }
}
