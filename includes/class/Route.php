<?php
    /**
     * Class: Route
     * Holds information for URLs, redirecting, etc.
     */
    class Route {
        # String: $action
        # The current action.
        public $action = "";

        # Array: $try
        # An array of (string) actions to try until one doesn't return false.
        public $try = array();

        # Boolean: $ajax
        # Shortcut to the AJAX constant (useful for Twig).
        public $ajax = AJAX;

        # Boolean: $success
        # Did <Route.init> call a successful route?
        public $success = false;

        # Variable: $controller
        # The Route's Controller.
        public $controller;

        /**
         * Function: __construct
         * Parse the URL and to determine what to do.
         *
         * Parameters:
         *     $controller - The controller to use.
         */
        private function __construct($controller) {
            $this->controller = $controller;

            $config = Config::current();

            if (substr_count($_SERVER['REQUEST_URI'], "..") > 0 )
                exit("GTFO.");
            elseif (isset($_GET['action']) and preg_match("/[^(\w+)]/", $_GET['action']))
                exit("Nope!");

            $this->action =& $_GET['action'];

            if (isset($_GET['feed']))
                $this->feed = true;

            # Parse the current URL and extract information.
            $parse = parse_url($config->url);
            fallback($parse["path"], "/");

            if (isset($controller->base))
                $parse["path"] = trim($parse["path"], "/")."/".trim($controller->base, "/")."/";

            $this->safe_path = str_replace("/", "\\/", $parse["path"]);
            $this->request = $parse["path"] == "/" ?
                                 $_SERVER['REQUEST_URI'] :
                                 preg_replace("/{$this->safe_path}?/", "", $_SERVER['REQUEST_URI'], 1) ;
            $this->arg = array_map("urldecode", explode("/", trim($this->request, "/")));

            if (substr_count($this->arg[0], "?") > 0 and !preg_match("/\?\w+/", $this->arg[0]))
                exit("No-Go!");

            if (method_exists($controller, "parse"))
                $controller->parse($this);

            Trigger::current()->call("parse_url", $this);

            $this->try[] = isset($this->action) ?
                               oneof($this->action, "index") :
                               (!substr_count($this->arg[0], "?") ?
                                   oneof(@$this->arg[0], "index") :
                                   "index") ;

            # Guess the action initially.
            # This is only required because of the view_site permission;
            # it has to know if they're viewing /login, in which case
            # it should allow the page to display.
            fallback($this->action, end($this->try));
        }

        /**
         * Function: init
         * Attempt Controller actions until one of them doesn't return false.
         *
         * This will also call the @[controllername]_xxxxx@ and @route_xxxxx@ triggers.
         */
        public function init() {
            $trigger = Trigger::current();

            $trigger->call("route_init", $this);

            $try = $this->try;

            if (isset($this->action))
                array_unshift($try, $this->action);

            $count = 0;
            foreach ($try as $key => $val) {
                if (is_numeric($key))
                    list($method, $args) = array($val, array());
                else
                    list($method, $args) = array($key, $val);

                $this->action = $method;

                $name = strtolower(str_replace("Controller", "", get_class($this->controller)));
                if ($trigger->exists($name."_".$method) or $trigger->exists("route_".$method))
                    $call = $trigger->call(array($name."_".$method, "route_".$method), $this->controller);
                else
                    $call = false;

                if ($call !== true and method_exists($this->controller, $method))
                    $response = call_user_func_array(array($this->controller, $method), $args);
                else
                    $response = false;

                if ($response !== false or $call !== false) {
                    $this->success = true;
                    break;
                }

                if (++$count == count($try) and isset($this->controller->fallback) and method_exists($this->controller, "display"))
                    call_user_func_array(array($this->controller, "display"), $this->controller->fallback);
            }

            if ($this->action != "login" and $this->success)
                $_SESSION['redirect_to'] = self_url();

            $trigger->call("route_done", $this);

            return true;
        }

        /**
         * Function: url
         * Attempts to change the specified clean URL to a dirty URL if clean URLs is disabled.
         * Use this for linking to things. The applicable URL conversions are passed through the
         * parse_urls trigger.
         *
         * Parameters:
         *     $url - The clean URL.
         *     $use_chyrp_url - Use @Config.chyrp_url@ instead of @Config.url@, when the @$url@ begins with "/"?
         *
         * Returns:
         *     A clean or dirty URL, depending on @Config.clean_urls@.
         */
        public function url($url, $controller = null) {
            $config = Config::current();

            if ($url[0] == "/")
                return (ADMIN ?
                           $config->chyrp_url.$url :
                           $config->url.$url);
            else
                $url = substr($url, -1) == "/" ? $url : $url."/" ;

            fallback($controller, $this->controller);

            $base = !empty($controller->base) ? $config->url."/".$controller->base : $config->url ;

            if ($config->clean_urls) { # If their post URL doesn't have a trailing slash, remove it from these as well.
                if (substr($url, 0, 5) == "page/") # Different URL for viewing a page
                    $url = substr($url, 5);

                return (substr($config->post_url, -1) == "/" or $url == "search/") ?
                           $base."/".$url :
                           $base."/".rtrim($url, "/") ;
            }

            $urls = fallback($controller->urls, array());
            Trigger::current()->filter($urls, "parse_urls");

            foreach (array_diff_assoc($urls, $controller->urls) as $key => $value)
                $urls[substr($key, 0, -1).preg_quote("feed/", $key[0]).$key[0]] = "/".$value."&amp;feed";

            $urls["|/([^/]+)/$|"] = "/?action=$1";

            return $base.fix(preg_replace(array_keys($urls), array_values($urls), "/".$url, 1));
        }

        /**
         * Function: add
         * Adds a route to Chyrp. Only needed for actions that have more than one parameter.
         * For example, for /tags/ you won't need to do this, but you will for /tag/tag-name/.
         *
         * Parameters:
         *     $path - The path to add. Wrap variables with parentheses, e.g. "tag/(name)/".
         *     $action - The action the path points to.
         *
         * See Also:
         *     <remove>
         */
        public function add($path, $action) {
            $config = Config::current();

            $new_routes = $config->routes;
            $new_routes[$path] = $action;

            $config->set("routes", $new_routes);
        }

        /**
         * Function: remove
         * Removes a route added by <add>.
         *
         * Parameters:
         *     $path - The path to remove.
         *
         * See Also:
         *     <add>
         */
        public function remove($path) {
            $config = Config::current();

            unset($config->routes[$path]);

            $config->set("routes", $config->routes);
        }

        /**
         * Function: current
         * Returns a singleton reference to the current class.
         */
        public static function & current($controller = null) {
            static $instance = null;

            if (!isset($controller) and empty($instance))
                error(__("Error"), __("Route was initiated without a Controller."), debug_backtrace());

            return $instance = (empty($instance)) ? new self($controller) : $instance ;
        }
    }

