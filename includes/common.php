<?php
    /**
     * File: Common
     *
     * Chyrp Lite: An ultra-lightweight blogging engine.
     *
     * Version:
     *     v2016.04
     *
     * Copyright:
     *     Chyrp Lite is Copyright 2008-2016 Alex Suraci, Arian Xhezairi,
     *     Daniel Pimley, and other contributors.
     *
     * License:
     *     Permission is hereby granted, free of charge, to any person
     *     obtaining a copy of this software and associated documentation
     *     files (the "Software"), to deal in the Software without
     *     restriction, including without limitation the rights to use,
     *     copy, modify, merge, publish, distribute, sublicense, and/or
     *     sell copies of the Software, and to permit persons to whom the
     *     Software is furnished to do so, subject to the following
     *     conditions:
     *
     *     The above copyright notice and this permission notice shall be
     *     included in all copies or substantial portions of the Software.
     *
     *     THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
     *     EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES
     *     OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
     *     NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT
     *     HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY,
     *     WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
     *     FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR
     *     OTHER DEALINGS IN THE SOFTWARE.
     *
     *     Except as contained in this notice, the name(s) of the above
     *     copyright holders shall not be used in advertising or otherwise
     *     to promote the sale, use or other dealings in this Software
     *     without prior written authorization.
     */

    # Constant: DEBUG
    # Should Chyrp use debugging processes?
    define('DEBUG', false);

    # Constant: CHYRP_VERSION
    # Version number for this release.
    define('CHYRP_VERSION', "2016.04");

    # Constant: CHYRP_CODENAME
    # The codename for this version.
    define('CHYRP_CODENAME', "Iago");

    # Constant: CHYRP_IDENTITY
    # The string identifying this version.
    define('CHYRP_IDENTITY', "Chyrp/".CHYRP_VERSION." (".CHYRP_CODENAME.")");

    # Constant: CACHE_TWIG
    # Override DEBUG to enable Twig template caching.
    define('CACHE_TWIG', true);

    # Constant: JAVASCRIPT
    # Are we serving a JavaScript file?
    if (!defined('JAVASCRIPT'))
        define('JAVASCRIPT', false);

    # Constant: MAIN
    # Is this being run from index.php?
    if (!defined('MAIN'))
        define('MAIN', false);

    # Constant: ADMIN
    # Is the user in the admin area?
    if (!defined('ADMIN'))
        define('ADMIN', false);

    # Constant: AJAX
    # Is this being run from an AJAX request?
    if (!defined('AJAX'))
        define('AJAX', !empty($_POST['ajax']));

    # Constant: XML_RPC
    # Is this being run from XML-RPC?
    if (!defined('XML_RPC'))
        define('XML_RPC', false);

    # Constant: UPGRADING
    # Is the user running the upgrader? (false)
    define('UPGRADING', false);

    # Constant: INSTALLING
    # Is the user running the installer? (false)
    define('INSTALLING', false);

    # Constant: TESTER
    # Is the site being run by the automated tester?
    define('TESTER', isset($_SERVER['HTTP_USER_AGENT']) and $_SERVER['HTTP_USER_AGENT'] == "TESTER");

    # Constant: DIR
    # Native directory separator.
    define('DIR', DIRECTORY_SEPARATOR);

    # Constant: MAIN_DIR
    # Absolute path to the Chyrp root.
    define('MAIN_DIR', dirname(dirname(__FILE__)));

    # Constant: INCLUDES_DIR
    # Absolute path to /includes.
    define('INCLUDES_DIR', MAIN_DIR.DIR."includes");

    # Constant: CACHES_DIR
    # Absolute path to /includes/caches.
    define('CACHES_DIR', INCLUDES_DIR.DIR."caches");

    # Constant: MODULES_DIR
    # Absolute path to /modules.
    define('MODULES_DIR', MAIN_DIR.DIR."modules");

    # Constant: FEATHERS_DIR
    # Absolute path to /feathers.
    define('FEATHERS_DIR', MAIN_DIR.DIR."feathers");

    # Constant: THEMES_DIR
    # Absolute path to /themes.
    define('THEMES_DIR', MAIN_DIR.DIR."themes");

    # Constant: UPDATE_XML
    # URL to the update feed.
    define('UPDATE_XML', "http://chyrplite.net/update.xml");

    # Constant: UPDATE_INTERVAL
    # Interval in seconds between update checks.
    define('UPDATE_INTERVAL', 86400);

    # Constant: UPDATE_PAGE
    # URL to the list of releases.
    define('UPDATE_PAGE', "https://github.com/xenocrat/chyrp-lite/releases");

    # Constant: USE_OB
    # Use output buffering?
    if (!defined('USE_OB'))
        define('USE_OB', true);

    # Constant: HTTP_ACCEPT_DEFLATE
    # Does the user agent accept deflate encoding?
    if (isset($_SERVER['HTTP_ACCEPT_ENCODING']) and substr_count($_SERVER['HTTP_ACCEPT_ENCODING'], "deflate"))
        define('HTTP_ACCEPT_DEFLATE', true);
    else
        define('HTTP_ACCEPT_DEFLATE', false);

    # Constant: HTTP_ACCEPT_GZIP
    # Does the user agent accept gzip encoding?
    if (isset($_SERVER['HTTP_ACCEPT_ENCODING']) and substr_count($_SERVER['HTTP_ACCEPT_ENCODING'], "gzip"))
        define('HTTP_ACCEPT_GZIP', true);
    else
        define('HTTP_ACCEPT_GZIP', false);

    # Constant: USE_ZLIB
    # Use zlib to provide content compression? See Also: http://bugs.php.net/55544
    if (!defined('USE_ZLIB'))
        if (!AJAX and (HTTP_ACCEPT_DEFLATE or HTTP_ACCEPT_GZIP) and extension_loaded("zlib") and
            !ini_get("zlib.output_compression") and (version_compare(PHP_VERSION, "5.4.6", ">=") or
                                                     version_compare(PHP_VERSION, "5.4.0", "<")))
            define('USE_ZLIB', true);
        else
            define('USE_ZLIB', false);

    # Constant: JSON_PRETTY_PRINT
    # Define a safe value to avoid warnings pre-5.4.
    if (!defined('JSON_PRETTY_PRINT'))
        define('JSON_PRETTY_PRINT', 0);

    # Constant: JSON_UNESCAPED_SLASHES
    # Define a safe value to avoid warnings pre-5.4.
    if (!defined('JSON_UNESCAPED_SLASHES'))
        define('JSON_UNESCAPED_SLASHES', 0);

    # Start output buffering and set header.
    if (USE_OB) {
        if (USE_ZLIB)
            ob_start("ob_gzhandler");
        else
            ob_start();

        if (USE_ZLIB and HTTP_ACCEPT_DEFLATE)
            header("Content-Encoding: deflate");

        if (USE_ZLIB and HTTP_ACCEPT_GZIP)
            header("Content-Encoding: gzip");
    }

    # File: Error
    # Error handling functions.
    require_once INCLUDES_DIR.DIR."error.php";

    # File: Helpers
    # Various functions used throughout the codebase.
    require_once INCLUDES_DIR.DIR."helpers.php";

    # File: Controller
    # Defines the Controller interface.
    require_once INCLUDES_DIR.DIR."interface".DIR."Controller.php";

    # File: Feather
    # See Also:
    require_once INCLUDES_DIR.DIR."interface".DIR."Feather.php";

    # File: Captcha
    # Defines the Captcha interface.
    require_once INCLUDES_DIR.DIR."interface".DIR."Captcha.php";

    # File: Config
    # See Also:
    #     <Config>
    require_once INCLUDES_DIR.DIR."class".DIR."Config.php";

    # File: SQL
    # See Also:
    #     <SQL>
    require_once INCLUDES_DIR.DIR."class".DIR."SQL.php";

    # File: Model
    # See Also:
    #     <Model>
    require_once INCLUDES_DIR.DIR."class".DIR."Model.php";

    # File: User
    # See Also:
    #     <User>
    require_once INCLUDES_DIR.DIR."model".DIR."User.php";

    # File: Visitor
    # See Also:
    #     <Visitor>
    require_once INCLUDES_DIR.DIR."model".DIR."Visitor.php";

    # File: Post
    # See Also:
    #     <Post>
    require_once INCLUDES_DIR.DIR."model".DIR."Post.php";

    # File: Page
    # See Also:
    #     <Page>
    require_once INCLUDES_DIR.DIR."model".DIR."Page.php";

    # File: Group
    # See Also:
    #     <Group>
    require_once INCLUDES_DIR.DIR."model".DIR."Group.php";

    # File: Session
    # See Also:
    #     <Session>
    require_once INCLUDES_DIR.DIR."class".DIR."Session.php";

    # File: Flash
    # See Also:
    #     <Flash>
    require_once INCLUDES_DIR.DIR."class".DIR."Flash.php";

    # File: Theme
    # See Also:
    #     <Theme>
    require_once INCLUDES_DIR.DIR."class".DIR."Theme.php";

    # File: Trigger
    # See Also:
    #     <Trigger>
    require_once INCLUDES_DIR.DIR."class".DIR."Trigger.php";

    # File: Module
    # See Also:
    #     <Module>
    require_once INCLUDES_DIR.DIR."class".DIR."Modules.php";

    # File: Feathers
    # See Also:
    #     <Feathers>
    require_once INCLUDES_DIR.DIR."class".DIR."Feathers.php";

    # File: Paginator
    # See Also:
    #     <Paginator>
    require_once INCLUDES_DIR.DIR."class".DIR."Paginator.php";

    # File: Route
    # See Also:
    #     <Route>
    require_once INCLUDES_DIR.DIR."class".DIR."Route.php";

    # File: Update
    # See Also:
    #     <Update>
    require_once INCLUDES_DIR.DIR."class".DIR."Update.php";

    # File: Main
    # See Also:
    #     <Controller>
    require_once INCLUDES_DIR.DIR."controller".DIR."Main.php";

    # File: Admin
    # See Also:
    #     <Controller>
    require_once INCLUDES_DIR.DIR."controller".DIR."Admin.php";

    # Handle a missing config file with redirect or error.
    if (!file_exists(INCLUDES_DIR.DIR."config.json.php"))
        if (!TESTER and MAIN and file_exists(MAIN_DIR.DIR."install.php"))
            redirect("install.php");
        else
            error(__("Error"), __("This resource cannot respond because it is not configured."), null, 501);

    # Start the timer that keeps track of Chyrp's load time.
    timer_start();

    # Register our autoloader.
    spl_autoload_register("autoload");

    # Load the config settings.
    $config = Config::current();

    # Prepare the SQL interface.
    $sql = SQL::current();

    # Set the timezone for date(), etc.
    set_timezone($config->timezone);

    # Initialize connection to SQL server.
    $sql->connect();

    # Sanitize all input depending on magic_quotes_gpc's enabled status.
    sanitize_input($_GET);
    sanitize_input($_POST);
    sanitize_input($_COOKIE);
    sanitize_input($_REQUEST);

    # Begin the session.
    session();

    # Set the locale.
    set_locale($config->locale);

    # Load the translation engine.
    load_translator("chyrp", INCLUDES_DIR.DIR."locale");

    # Constant: PREVIEWING
    # Is the user previewing a theme?
    define('PREVIEWING', !ADMIN and !empty($_SESSION['theme']));

    # Constant: THEME_DIR
    # Absolute path to the theme (current or previewed).
    define('THEME_DIR', MAIN_DIR.DIR."themes".DIR.(PREVIEWING ? $_SESSION['theme'] : $config->theme));

    # Constant: THEME_URL
    # Absolute URL to the theme (current or previewed).
    define('THEME_URL', $config->chyrp_url."/themes/".(PREVIEWING ? $_SESSION['theme'] : $config->theme));

    # Initialize the theme.
    $theme = Theme::current();

    # Load the Visitor.
    $visitor = Visitor::current();

    # Prepare the notifier.
    $flash = Flash::current();

    # Initiate the extensions.
    init_extensions();

    # Prepare the trigger class.
    $trigger = Trigger::current();

    # Filter the visitor immediately after the Modules are initialized.
    $trigger->filter($visitor, "visitor");

    # First general-purpose trigger.
    $trigger->call("runtime");

    # Publish scheduled posts.
    if (MAIN or ADMIN)
        Post::publish_scheduled();

    # Set the content-type and charset.
    if (JAVASCRIPT) {
        header("Content-Type: application/javascript");
        header("Cache-Control: no-cache, must-revalidate");
        header("Expires: Mon, 03 Jun 1991 05:30:00 GMT");
    } else {
        header("Content-Type: text/html; charset=UTF-8");
        header("X-Pingback: ".$config->chyrp_url."/includes/rpc.php");
    }

    # Be sociable but safe if the site is using the HTTPS protocol.
    if (!empty($_SERVER['HTTPS']) and $_SERVER['HTTPS'] !== "off" or $_SERVER['SERVER_PORT'] == 443)
        header("Referrer-Policy: origin-when-cross-origin");
