<?php
    /**
     * File: Common
     *
     * Chyrp Lite - a lightweight blogging engine
     *
     * Version:
     *     v2.6
     *
     * License:
     *     Modified MIT (See COPYING)
     *
     * Chyrp Copyright:
     *     Copyright (c) 2012 Chyrp Team (See AUTHORS)
     */

    # Constant: CHYRP_VERSION
    # Chyrp's version number.
    define('CHYRP_VERSION', "2.6");

    # Constant: DEBUG
    # Should Chyrp use debugging processes?
    define('DEBUG', true);

    # Constant: CACHE_TWIG
    # If defined, this will take priority over DEBUG and toggle Twig template caching.
    # Do not enable this during theme development.
    define('CACHE_TWIG', true);

    # Constant: JAVASCRIPT
    # Is this the JavaScript file?
    if (!defined('JAVASCRIPT')) define('JAVASCRIPT', false);

    # Constant: ADMIN
    # Is the user in the admin area?
    if (!defined('ADMIN')) define('ADMIN', false);

    # Constant: AJAX
    # Is this being run from an AJAX request?
    if (!defined('AJAX')) define('AJAX', isset($_POST['ajax']) and $_POST['ajax'] == "true");

    # Constant: XML_RPC
    # Is this being run from XML-RPC?
    if (!defined('XML_RPC')) define('XML_RPC', false);

    # Constant: TRACKBACK
    # Is this being run from a trackback request?
    if (!defined('TRACKBACK')) define('TRACKBACK', false);

    # Constant: UPGRADING
    # Is the user running the upgrader? (false)
    define('UPGRADING', false);

    # Constant: INSTALLING
    # Is the user running the installer? (false)
    define('INSTALLING', false);

    # Constant: TESTER
    # Is the site being run by the automated tester?
    define('TESTER', isset($_SERVER['HTTP_USER_AGENT']) and $_SERVER['HTTP_USER_AGENT'] == "tester.rb");

    # Constant: INDEX
    # Is the requested file /index.php?
    define('INDEX', (pathinfo($_SERVER['SCRIPT_NAME'], PATHINFO_BASENAME) == "index.php") and !ADMIN);

    # Constant: MAIN_DIR
    # Absolute path to the Chyrp root
    define('MAIN_DIR', dirname(dirname(__FILE__)));

    # Constant: INCLUDES_DIR
    # Absolute path to /includes
    define('INCLUDES_DIR', MAIN_DIR."/includes");

    # Constant: MODULES_DIR
    # Absolute path to /modules
    define('MODULES_DIR', MAIN_DIR."/modules");

    # Constant: FEATHERS_DIR
    # Absolute path to /feathers
    define('FEATHERS_DIR', MAIN_DIR."/feathers");

    # Constant: THEMES_DIR
    # Absolute path to /themes
    define('THEMES_DIR', MAIN_DIR."/themes");

    # Constant: ADMIN_THEMES_DIR
    # Absolute path to /admin/themes
    define('ADMIN_THEMES_DIR', MAIN_DIR."/admin/themes");

    # Constant: USE_ZLIB
    # Use zlib to provide GZIP compression
    if (version_compare(PHP_VERSION, "5.4.4", "<")) define('USE_ZLIB', true);
    else define('USE_ZLIB', false);

    # Set error reporting levels, and headers for Chyrp's JS files.
    if (JAVASCRIPT) {
        error_reporting(0);
        header("Content-Type: application/x-javascript");
        header("Cache-Control: no-cache, must-revalidate");
        header("Expires: Mon, 03 Jun 1991 05:30:00 GMT");
    } else
        error_reporting(E_ALL | E_STRICT); # Make sure E_STRICT is on so Chyrp remains errorless.

    # Use GZip compression if available.
    if (!AJAX and
        extension_loaded("zlib") and
        !ini_get("zlib.output_compression") and
        isset($_SERVER['HTTP_ACCEPT_ENCODING']) and
        substr_count($_SERVER['HTTP_ACCEPT_ENCODING'], "gzip") and
        USE_ZLIB) {
        ob_start("ob_gzhandler");
        header("Content-Encoding: gzip");
    } else
        ob_start();

    # File: Helpers
    # Various functions used throughout Chyrp's code.
    require_once INCLUDES_DIR."/helpers.php";

    # File: Gettext
    # Gettext library.
    require_once INCLUDES_DIR."/lib/gettext/gettext.php";

    # File: Streams
    # Streams library.
    require_once INCLUDES_DIR."/lib/gettext/streams.php";

    # File: YAML
    # Horde YAML parsing library.
    require_once INCLUDES_DIR."/lib/YAML.php";

    # File: PasswordHash
    # Secure hashing of user passwords
    require_once INCLUDES_DIR."/lib/PasswordHash.php";

    # File: Config
    # See Also:
    #     <Config>
    require_once INCLUDES_DIR."/class/Config.php";

    # File: SQL
    # See Also:
    #     <SQL>
    require_once INCLUDES_DIR."/class/SQL.php";

    # File: Model
    # See Also:
    #     <Model>
    require_once INCLUDES_DIR."/class/Model.php";

    # File: User
    # See Also:
    #     <User>
    require_once INCLUDES_DIR."/model/User.php";

    # File: Visitor
    # See Also:
    #     <Visitor>
    require_once INCLUDES_DIR."/model/Visitor.php";

    # File: Post
    # See Also:
    #     <Post>
    require_once INCLUDES_DIR."/model/Post.php";

    # File: Page
    # See Also:
    #     <Page>
    require_once INCLUDES_DIR."/model/Page.php";

    # File: Group
    # See Also:
    #     <Group>
    require_once INCLUDES_DIR."/model/Group.php";

    # File: Session
    # See Also:
    #     <Session>
    require_once INCLUDES_DIR."/class/Session.php";

    # File: Flash
    # See Also:
    #     <Flash>
    require_once INCLUDES_DIR."/class/Flash.php";

    # File: Theme
    # See Also:
    #     <Theme>
    require_once INCLUDES_DIR."/class/Theme.php";

    # File: Trigger
    # See Also:
    #     <Trigger>
    require_once INCLUDES_DIR."/class/Trigger.php";

    # File: Module
    # See Also:
    #     <Module>
    require_once INCLUDES_DIR."/class/Modules.php";

    # File: Feathers
    # See Also:
    #     <Feathers>
    require_once INCLUDES_DIR."/class/Feathers.php";

    # File: Paginator
    # See Also:
    #     <Paginator>
    require_once INCLUDES_DIR."/class/Paginator.php";

    # File: Twig
    # Chyrp's templating engine.
    require_once INCLUDES_DIR."/class/Twig.php";

    # File: Route
    # See Also:
    #     <Route>
    require_once INCLUDES_DIR."/class/Route.php";

    # File: Update
    # See Also:
    #     <Update>
    require_once INCLUDES_DIR."/class/Update.php";

    # File: Main
    # See Also:
    #     <Main Controller>
    require_once INCLUDES_DIR."/controller/Main.php";

    # File: Admin
    # See Also:
    #     <Admin Controller>
    require_once INCLUDES_DIR."/controller/Admin.php";

    # File: Feather
    # See Also:
    #     <Feather>
    require_once INCLUDES_DIR."/interface/Feather.php";

    # Set the error handler to exit on error if this is being run from the tester.
    if (TESTER)
        set_error_handler("error_panicker");

    # Redirect to the installer if there is no config.
    if (!file_exists(INCLUDES_DIR."/config.yaml.php"))
        redirect("install.php");

    # Start the timer that keeps track of Chyrp's load time.
    timer_start();

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

    # Set the locale for gettext.
    set_locale($config->locale);

    # Load the translation engine.
    load_translator("chyrp", INCLUDES_DIR."/locale/".$config->locale.".mo");

    # Constant: PREVIEWING
    # Is the user previewing a theme?
    define('PREVIEWING', !ADMIN and !empty($_SESSION['theme']));

    # Constant: THEME_DIR
    # Absolute path to /themes/(current/previewed theme)
    define('THEME_DIR', MAIN_DIR."/themes/".(PREVIEWING ? $_SESSION['theme'] : $config->theme));

    # Constant: THEME_URL
    # URL to /themes/(current/previewed theme)
    define('THEME_URL', $config->chyrp_url."/themes/".(PREVIEWING ? $_SESSION['theme'] : $config->theme));

    # Initialize the theme.
    $theme = Theme::current();

    # Load the Visitor.
    $visitor = Visitor::current();

    $captchaHooks = array();

    # Prepare the notifier.
    $flash = Flash::current();

    # Initiate the extensions.
    init_extensions();

    # Prepare the trigger class
    $trigger = Trigger::current();

    # Filter the visitor immediately after the Modules are initialized.
    # Example usage scenario: custom auth systems (e.g. OpenID)
    $trigger->filter($visitor, "visitor");

    # First general-purpose trigger. There are many cases you may want to use @route_init@ instead of this, however.
    $trigger->call("runtime");

    # Set the content-type to the theme's "type" setting, or "text/html".
    header("Content-type: ".(INDEX ? fallback($theme->type, "text/html") : "text/html")."; charset=UTF-8");
