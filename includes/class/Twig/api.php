<?php
/**
 * Twig::API
 * ~~~~~~~~~
 *
 * The High-Level API
 *
 * :copyright: 2008 by Armin Ronacher.
 * :license: BSD.
 */


/**
 * Load the compiler system.  Call this before you access the
 * compiler!
 */
function twig_load_compiler()
{
    if (!defined('TWIG_COMPILER_INCLUDED'))
        require TWIG_BASE . '/compiler.php';
}


/**
 * A helper function that can be used by filters to get the
 * current active template.  This use useful to access variables
 * on the template like the charset.
 */
function twig_get_current_template()
{
    return $GLOBALS['twig_current_template'];
}


/* the current template that is rendered.  This used used internally
   and is an implementation detail.  Don't tamper with that. */
$twig_current_template = NULL;


/**
 * This class wraps a template instance as returned by the compiler and
 * is usually constructed from the `Twig_Loader`.
 */
class Twig_Template
{
    private $instance;
    public $charset;
    public $loader;

    public function __construct($instance, $charset=NULL, $loader)
    {
        $this->instance = $instance;
        $this->charset = $charset;
        $this->loader = $loader;
    }

    /**
     * Render the template with the given context and return it
     * as string.
     */
    public function render($context=NULL)
    {
        ob_start();
        $this->display($context);
        return ob_get_clean();
    }

    /**
     * Works like `render()` but prints the output.
     */
    public function display($context=NULL)
    {
        global $twig_current_template;
        $old = $twig_current_template;
        $twig_current_template = $this;
        if (is_null($context))
            $context = array();
        $this->instance->render($context);
        $twig_current_template = $old;
    }
}

/**
 * Baseclass for custom loaders.  Subclasses have to provide a
 * getFilename method.
 */
class Twig_BaseLoader
{
    public $cache;
    public $charset;

    public function __construct($cache=NULL, $charset=NULL)
    {
        $this->cache = $cache;
        $this->charset = $charset;
    }

    public function getTemplate($name)
    {
        $cls = $this->requireTemplate($name);
        return new Twig_Template(new $cls, $this->charset, $this);
    }

    public function getCacheFilename($name)
    {
        return $this->cache . '/twig_' . md5($name) . '.cache';
    }

    public function requireTemplate($name)
    {
        $cls = '__TwigTemplate_' . md5($name);
        if (!class_exists($cls)) {
            if (is_null($this->cache)) {
                $this->evalTemplate($name);
                return $cls;
            }
            $fn = $this->getFilename($name);
            if (!file_exists($fn))
                throw new Twig_TemplateNotFound($name);
            $cache_fn = $this->getCacheFilename($name);
            if (!file_exists($cache_fn) ||
                filemtime($cache_fn) < filemtime($fn)) {
                twig_load_compiler();
                $fp = @fopen($cache_fn, 'wb');
                if (!$fp) {
                    $this->evalTemplate($name, $fn);
                    return $cls;
                }
                $compiler = new Twig_FileCompiler($fp);
                $this->compileTemplate($name, $compiler, $fn);
                fclose($fp);
            }
            include $cache_fn;
        }
        return $cls;
    }

    public function compileTemplate($name, $compiler=NULL, $fn=NULL)
    {
        twig_load_compiler();
        if (is_null($compiler)) {
            $compiler = new Twig_StringCompiler();
            $returnCode = true;
        }
        else
            $returnCode = false;
        if (is_null($fn))
            $fn = $this->getFilename($name);

        $node = twig_parse(file_get_contents($fn, $name), $name);
        $node->compile($compiler);
        if ($returnCode)
            return $compiler->getCode();
    }

    private function evalTemplate($name, $fn=NULL)
    {
        $code = $this->compileTemplate($name, NULL, $fn);
        # echo "ORIGINAL: <textarea rows=15 style=\"width: 100%\">".fix(print_r($code, true))."</textarea>";
        $code = preg_replace('/(?!echo twig_get_attribute.+)echo "[\\\\tn]+";/', "", $code); # Remove blank lines
        #echo "STRIPPED: <textarea rows=15 style=\"width: 100%\">".fix(print_r($code, true))."</textarea>";
        eval('?>' . $code);
    }
}


/**
 * Helper class that loads templates.
 */
class Twig_Loader extends Twig_BaseLoader
{
    public $folder;

    public function __construct($folder, $cache=NULL, $charset=NULL)
    {
        parent::__construct($cache, $charset);
        $this->folder = $folder;
    }

    public function getFilename($name)
    {
        if ($name[0] == '/' or preg_match("/[a-zA-Z]:\\\/", $name)) return $name;

        $path = array();
        foreach (explode('/', $name) as $part) {
            if ($part[0] != '.')
                array_push($path, $part);
        }

        return $this->folder . '/' .  implode('/', $path) ;
    }
}
