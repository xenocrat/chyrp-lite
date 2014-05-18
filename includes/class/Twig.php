<?php
    /**
     * Twig
     * ~~~~
     *
     * A simple cross-language template engine.
     *
     * Usage
     * -----
     *
     * Using Twig in a application is straightfoward.  All you have to do is to
     * make sure you have a folder where all your templates go and another one
     * where the compiled templates go (which we call the cache)::
     *
     *  require 'Twig.php';
     *  $loader = new Twig_Loader('path/to/templates', 'path/to/cache');
     *
     * After that you can load templates using the loader::
     *
     *  $template = $loader->getTemplate('index.html');
     *
     * You can render templates by using the render and display methods.  display
     * works like render just that it prints the output whereas render returns
     * the generated source as string.  Both accept an array as context::
     *
     *  echo $template->render(array('users' => get_list_of_users()));
     *  $template->display(array('users' => get_list_of_users()));
     *
     * Custom Loaders
     * --------------
     *
     * For many applications it's a good idea to subclass the loader to add
     * support for multiple template locations.  For example many applications
     * support plugins and you want to allow plugins to ship themes.
     *
     * The easiest way is subclassing Twig_Loader and override the getFilename
     * method which calculates the path to the template on the file system.
     *
     *
     * :copyright: 2008 by Armin Ronacher.
     * :license: BSD.
     */


    if (!defined('TWIG_BASE'))
        define('TWIG_BASE', dirname(__FILE__) . '/Twig');

    define('TWIG_VERSION', '0.1-dev');


    // the systems we load automatically on initialization.  The compiler
    // and other stuff is loaded on first request.
    require TWIG_BASE . '/exceptions.php';
    require TWIG_BASE . '/runtime.php';
    require TWIG_BASE . '/api.php';
