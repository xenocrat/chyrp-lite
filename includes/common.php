<?php
    /**
     * File: common
     * Configures the Chyrp Lite environment.
     */

    # Constant: DEBUG
    # Should Chyrp use debugging processes?
    define('DEBUG', file_exists(dirname(__FILE__).DIRECTORY_SEPARATOR."DEBUG"));

    # Constant: CHYRP_VERSION
    # Version number for this release.
    define('CHYRP_VERSION', "2025.03");

    # Constant: CHYRP_CODENAME
    # The codename for this version.
    define('CHYRP_CODENAME', "Bridled");

    # Constant: CHYRP_IDENTITY
    # The string identifying this version.
    define('CHYRP_IDENTITY', "Chyrp/".CHYRP_VERSION." (".CHYRP_CODENAME.")");

    # Constant: MAIN
    # Is <MainController> the route controller?
    if (!defined('MAIN'))
        define('MAIN', false);

    # Constant: ADMIN
    # Is <AdminController> the route controller?
    if (!defined('ADMIN'))
        define('ADMIN', false);

    # Constant: AJAX
    # Is <AjaxController> the route controller?
    if (!defined('AJAX'))
        define('AJAX', false);

    # Constant: UPGRADING
    # Is the user running the upgrader? (false)
    define('UPGRADING', false);

    # Constant: INSTALLING
    # Is the user running the installer? (false)
    define('INSTALLING', false);

    # Constant: COOKIE_LIFETIME
    # The lifetime of session cookies in seconds.
    define('COOKIE_LIFETIME', 2592000);

    # Constant: PASSWORD_RESET_TOKEN_LIFETIME
    # The lifetime of password reset tokens in seconds.
    define('PASSWORD_RESET_TOKEN_LIFETIME', 3600);

    # Constant: MAX_TIME_LIMIT
    # The maximum allowed execution time in seconds.
    define('MAX_TIME_LIMIT', 600);

    # Constant: MAX_MEMORY_LIMIT
    # The maximum amount of memory that can be allocated.
    define('MAX_MEMORY_LIMIT', "100M");

    # Constant: SQL_DATETIME_ZERO
    # The preferred SQL datetime "zero" value.
    define('SQL_DATETIME_ZERO', "1000-01-01 00:00:00");

    # Constant: SQL_DATETIME_ZERO_VARIANTS
    # An array of SQL datetime values corresponding to "zero".
    define('SQL_DATETIME_ZERO_VARIANTS',
        array(
            "0000-00-00 00:00:00",
            "0001-01-01 00:00:00",
            "1000-01-01 00:00:00"
        )
    );

    # Constant: BOT_UA
    # Are we being visited by a probable robot?
    define('BOT_UA',
        isset($_SERVER['HTTP_USER_AGENT']) and
        preg_match("/(bots?|crawler|slurp|spider)\b/i", $_SERVER['HTTP_USER_AGENT'])
    );

    # Constant: DIR
    # Native directory separator.
    define('DIR', DIRECTORY_SEPARATOR);

    # Constant: MAIN_DIR
    # Absolute path to the Chyrp root.
    define('MAIN_DIR', dirname(__FILE__, 2));

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
    define('UPDATE_XML', "https://chyrplite.net/rss/update.xml");

    # Constant: UPDATE_INTERVAL
    # Interval in seconds between update checks.
    define('UPDATE_INTERVAL', 86400);

    # Constant: UPDATE_PAGE
    # URL to the latest release.
    define('UPDATE_PAGE', "https://github.com/xenocrat/chyrp-lite/releases/latest");

    # Constant: SESSION_DENY_BOT
    # Deny session storage to robots?
    define('SESSION_DENY_BOT', true);

    # Constant: SLUG_STRICT
    # Use strict sanitization for slugs?
    define('SLUG_STRICT', true);

    # Constant: GET_REMOTE_UNSAFE
    # Allow get_remote() to connect to private and reserved IP addresses?
    define('GET_REMOTE_UNSAFE', false);

    # Constant: USE_GETTEXT_SHIM
    # Use a shim for translation support?
    define('USE_GETTEXT_SHIM', true);

    # Constant: USE_OB
    # Use output buffering?
    if (!defined('USE_OB'))
        define('USE_OB', true);

    # Constant: HTTP_ACCEPT_ZSTD
    # Does the user agent accept Zstandard encoding?
    define('HTTP_ACCEPT_ZSTD',
        isset($_SERVER['HTTP_ACCEPT_ENCODING']) and
        str_contains($_SERVER['HTTP_ACCEPT_ENCODING'], "zstd")
    );

    # Constant: HTTP_ACCEPT_DEFLATE
    # Does the user agent accept deflate encoding?
    define('HTTP_ACCEPT_DEFLATE',
        isset($_SERVER['HTTP_ACCEPT_ENCODING']) and
        str_contains($_SERVER['HTTP_ACCEPT_ENCODING'], "deflate")
    );

    # Constant: HTTP_ACCEPT_GZIP
    # Does the user agent accept gzip encoding?
    define('HTTP_ACCEPT_GZIP',
        isset($_SERVER['HTTP_ACCEPT_ENCODING']) and
        str_contains($_SERVER['HTTP_ACCEPT_ENCODING'], "gzip")
    );

    # Constant: CAN_USE_ZSTD
    # Can we use zstd to compress output?
    define('CAN_USE_ZSTD',
        HTTP_ACCEPT_ZSTD and extension_loaded("zstd")
    );

    # Constant: CAN_USE_ZLIB
    # Can we use zlib to compress output?
    define('CAN_USE_ZLIB',
        (HTTP_ACCEPT_DEFLATE or HTTP_ACCEPT_GZIP) and extension_loaded("zlib")
    );

    # Constant: USE_COMPRESSION
    # Use content compression for responses?
    if (!defined('USE_COMPRESSION'))
        define('USE_COMPRESSION',
            (CAN_USE_ZSTD or CAN_USE_ZLIB) and !ini_get("zlib.output_compression")
        );

    # Start output buffering and set the header.
    if (USE_OB) {
        if (USE_COMPRESSION) {
            if (CAN_USE_ZSTD) {
                ob_start(
                    function ($data) {
                        return zstd_compress($data, ZSTD_COMPRESS_LEVEL_DEFAULT);
                    }
                );
                header("Content-Encoding: zstd");
            } else {
                ob_start("ob_gzhandler");
                header("Content-Encoding: ".(HTTP_ACCEPT_GZIP ? "gzip" : "deflate"));
            }
        } else {
            ob_start();
        }
    }

    # Constant: OB_BASE_LEVEL
    # The base level of output buffering.
    define('OB_BASE_LEVEL', ob_get_level());

    # File: error
    # Functions for handling and reporting errors.
    require_once INCLUDES_DIR.DIR."error.php";

    # File: helpers
    # Various functions used throughout the codebase.
    require_once INCLUDES_DIR.DIR."helpers.php";

    # File: Controller
    # Defines the Controller interface.
    require_once INCLUDES_DIR.DIR."interface".DIR."Controller.php";

    # File: Feather
    # Defines the Feather interface.
    require_once INCLUDES_DIR.DIR."interface".DIR."Feather.php";

    # File: CaptchaProvider
    # Defines the CaptchaProvider interface.
    require_once INCLUDES_DIR.DIR."interface".DIR."CaptchaProvider.php";

    # File: FeedGenerator
    # Defines the FeedGenerator interface.
    require_once INCLUDES_DIR.DIR."interface".DIR."FeedGenerator.php";

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

    # File: Controllers
    # See Also:
    #     <Controllers>
    require_once INCLUDES_DIR.DIR."class".DIR."Controllers.php";

    # File: Modules
    # See Also:
    #     <Modules>
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

    # File: Translation
    # See Also:
    #     <Translation>
    require_once INCLUDES_DIR.DIR."class".DIR."Translation.php";

    # File: Main
    # See Also:
    #     <Controller>
    require_once INCLUDES_DIR.DIR."controller".DIR."Main.php";

    # File: Admin
    # See Also:
    #     <Controller>
    require_once INCLUDES_DIR.DIR."controller".DIR."Admin.php";

    # File: Ajax
    # See Also:
    #     <Controller>
    require_once INCLUDES_DIR.DIR."controller".DIR."Ajax.php";

    # Exit if an upgrade is in progress.
    if (file_exists(INCLUDES_DIR.DIR."upgrading.lock"))
        error(
            __("Service Unavailable"),
            __("This resource is temporarily unable to serve your request."),
            code:503
        );

    # Exit if the config file is missing.
    if (!file_exists(INCLUDES_DIR.DIR."config.json.php"))
        error(
            __("Service Unavailable"),
            __("This resource cannot respond because it is not configured."),
            code:503
        );

    # Start the timer that keeps track of Chyrp's load time.
    timer_start();

    # Load the config settings.
    $config = Config::current();

    # Prepare the SQL interface.
    $sql = SQL::current();

    # Register our autoloader.
    spl_autoload_register("autoload");

    # Register our feed alias.
    class_alias($config->feed_format, "BlogFeed");

    # Set the timezone for date(), etc.
    set_timezone($config->timezone);

    # Initialize connection to SQL server.
    $sql->connect();

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
    define('THEME_DIR',
        MAIN_DIR.DIR."themes".DIR.
        (PREVIEWING ? $_SESSION['theme'] : $config->theme)
    );

    # Constant: THEME_URL
    # Absolute URL to the theme (current or previewed).
    define('THEME_URL',
        $config->chyrp_url."/themes/".
        (PREVIEWING ? $_SESSION['theme'] : $config->theme)
    );

    # Instantiate the theme.
    $theme = Theme::current();

    # Instantiate notifications.
    $flash = Flash::current();

    # Instantiate triggers.
    $trigger = Trigger::current();

    # Initialize extensions.
    init_extensions();

    # Instantiate the visitor.
    $visitor = Visitor::current();

    # First general-purpose trigger.
    $trigger->call("runtime");

    # Publish scheduled posts.
    if (MAIN or ADMIN)
        Post::publish_scheduled(true);

    # Set headers.
    header("Content-Type: text/html; charset=UTF-8");
    header("Referrer-Policy: strict-origin-when-cross-origin");
    header("Vary: Accept-Encoding, Cookie, Save-Data");

    if ($config->send_pingbacks)
        header("Link: <".$config->url."/?action=webmention>; rel=\"webmention\"");
