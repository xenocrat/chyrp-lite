<?php
    /**
     * File: common
     *
     * BSD 3-Clause License
     *
     * Copyright 2008-2019 Alex Suraci, Arian Xhezairi, Daniel Pimley, and others.
     *
     * Redistribution and use in source and binary forms, with or without
     * modification, are permitted provided that the following conditions are met:
     *
     * 1. Redistributions of source code must retain the above copyright notice, this
     *    list of conditions and the following disclaimer.
     *
     * 2. Redistributions in binary form must reproduce the above copyright notice,
     *    this list of conditions and the following disclaimer in the documentation
     *    and/or other materials provided with the distribution.
     *
     * 3. Neither the name of the copyright holder nor the names of its
     *    contributors may be used to endorse or promote products derived from
     *    this software without specific prior written permission.
     *
     * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS"
     * AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
     * IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
     * DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE
     * FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL
     * DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR
     * SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER
     * CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY,
     * OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
     * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
     */

    # Constant: DEBUG
    # Should Chyrp use debugging processes?
    define('DEBUG', false);

    # Constant: CHYRP_VERSION
    # Version number for this release.
    define('CHYRP_VERSION', "2019.02");

    # Constant: CHYRP_CODENAME
    # The codename for this version.
    define('CHYRP_CODENAME', "Eurasian");

    # Constant: CHYRP_IDENTITY
    # The string identifying this version.
    define('CHYRP_IDENTITY', "Chyrp/".CHYRP_VERSION." (".CHYRP_CODENAME.")");

    # Constant: JAVASCRIPT
    # Are we serving a JavaScript file?
    if (!defined('JAVASCRIPT'))
        define('JAVASCRIPT', false);

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

    # Constant: XML_RPC
    # Is this request XML-RPC?
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

    # Constant: CACHE_TWIG
    # Enable Twig template caching.
    define('CACHE_TWIG', is_dir(CACHES_DIR.DIR."twig") and is_writable(CACHES_DIR.DIR."twig"));

    # Constant: CACHE_THUMBS
    # Enable image thumbnail caching.
    define('CACHE_THUMBS', is_dir(CACHES_DIR.DIR."thumbs") and is_writable(CACHES_DIR.DIR."thumbs"));

    # Constant: UPDATE_XML
    # URL to the update feed.
    define('UPDATE_XML', "https://chyrplite.net/rss/update.xml");

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
    define('HTTP_ACCEPT_DEFLATE',
        isset($_SERVER['HTTP_ACCEPT_ENCODING']) and
        substr_count($_SERVER['HTTP_ACCEPT_ENCODING'], "deflate"));

    # Constant: HTTP_ACCEPT_GZIP
    # Does the user agent accept gzip encoding?
    define('HTTP_ACCEPT_GZIP',
        isset($_SERVER['HTTP_ACCEPT_ENCODING']) and
        substr_count($_SERVER['HTTP_ACCEPT_ENCODING'], "gzip"));

    # Constant: USE_ZLIB
    # Use zlib to provide content compression?
    # See Also:
    #     http://bugs.php.net/55544
    if (!defined('USE_ZLIB'))
        define('USE_ZLIB',
            (HTTP_ACCEPT_DEFLATE or HTTP_ACCEPT_GZIP) and extension_loaded("zlib") and
            !ini_get("zlib.output_compression") and version_compare(PHP_VERSION, "5.4.6", ">="));

    # Start output buffering and set header.
    if (USE_OB)
        if (USE_ZLIB) {
            ob_start("ob_gzhandler");
            header("Content-Encoding: ".(HTTP_ACCEPT_GZIP ? "gzip" : "deflate"));
        } else {
            ob_start();
        }

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
        error(__("Service Unavailable"),
              __("This resource is temporarily unable to serve your request."), null, 503);

    # Exit if the config file is missing.
    if (!file_exists(INCLUDES_DIR.DIR."config.json.php"))
        error(__("Service Unavailable"),
              __("This resource cannot respond because it is not configured."), null, 503);

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
    define('THEME_DIR', MAIN_DIR.DIR."themes".DIR.(PREVIEWING ? $_SESSION['theme'] : $config->theme));

    # Constant: THEME_URL
    # Absolute URL to the theme (current or previewed).
    define('THEME_URL', $config->chyrp_url."/themes/".(PREVIEWING ? $_SESSION['theme'] : $config->theme));

    # Instantiate the theme.
    $theme = Theme::current();

    # Instantiate the visitor.
    $visitor = Visitor::current();

    # Instantiate notifications.
    $flash = Flash::current();

    # Initialize extensions.
    init_extensions();

    # Instantiate triggers.
    $trigger = Trigger::current();

    # Filter the visitor immediately after extensions are initialized.
    $trigger->filter($visitor, "visitor");

    # First general-purpose trigger.
    $trigger->call("runtime");

    # Publish scheduled posts.
    if (MAIN or ADMIN)
        Post::publish_scheduled();

    # Set appropriate headers.
    if (JAVASCRIPT) {
        header("Content-Type: application/javascript; charset=UTF-8");
        header("Referrer-Policy: no-referrer");
        header("Vary: Accept-Encoding, Cookie, Origin, Referer");
        header("Cache-Control: no-store, no-cache, must-revalidate");
        header("Expires: Mon, 03 Jun 1991 05:30:00 GMT");
    } else {
        header("Content-Type: text/html; charset=UTF-8");
        header("Referrer-Policy: strict-origin-when-cross-origin");
        header("Vary: Accept-Encoding, Cookie");
        header("X-Pingback: ".$config->chyrp_url."/includes/rpc.php");
    }

    # Upgrade future requests if the canonical URL is HTTPS.
    if (substr_count($config->url, "https://"))
        header("Strict-Transport-Security: max-age=31536000");
