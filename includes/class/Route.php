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
         * Parse the URL and give the controller an opportunity to determine the action.
         *
         * Parameters:
         *     $controller - The controller to use.
         */
        private function __construct($controller) {
            if (!in_array("Controller", class_implements($controller)))
                trigger_error(__("Route was initiated with an invalid Controller."), E_USER_WARNING);

            fallback($controller->protected, array("__construct", "parse", "display", "current"));

            $this->controller = $controller;

            $config = Config::current();

            if (substr_count($_SERVER['REQUEST_URI'], "..") > 0 )
                error(__("Error"), __("Malformed URI."), null, 400);

            if (isset($_GET['action']) and preg_match("/[^(\w+)]/", $_GET['action']))
                error(__("Error"), __("Invalid action."), null, 400);

            # Determining the action can be this simple if clean URLs are disabled.
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

            # Decompose clean URLs.
            $this->arg = array_map("urldecode", explode("/", trim($this->request, "/")));

            if (substr_count($this->arg[0], "?") > 0 and !preg_match("/\?\w+/", $this->arg[0]))
                error(__("Error"), __("Invalid action."), null, 400);

            # Give the controller an opportunity to parse this route and determine the action.
            $controller->parse($this);

            Trigger::current()->call("parse_url", $this);

            $this->try[] = isset($this->action) ?
                               oneof($this->action, "index") : (!substr_count($this->arg[0], "?") ?
                                   oneof($this->arg[0], "index") : "index") ;

            # Set the action, using a guess if necessary, to satisfy the view_site permission test.
            # A subset of actions is permitted even if the visitor is not allowed to view the site.
            fallback($this->action, end($this->try));
        }

        /**
         * Function: init
         * Attempt to call a responder for the action(s) until one of them doesn't return false.
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

                # This discovers responders provided by extensions.
                if ($trigger->exists($name."_".$method) or $trigger->exists("route_".$method))
                    $call = $trigger->call(array($name."_".$method, "route_".$method), $this->controller);
                else
                    $call = false;

                # Protect the controller's non-responder methods. PHP functions are not case-sensitive!
                foreach ($this->controller->protected as $protected)
                    if (strcasecmp($protected, $method) == 0)
                        continue 2;

                # This discovers responders native to the controller.
                if ($call !== true and method_exists($this->controller, $method))
                    $response = call_user_func_array(array($this->controller, $method), $args);
                else
                    $response = false;

                if ($response !== false or $call !== false) {
                    $this->success = true;
                    break;
                }

                # No responders were found; display a fallback template if one is set.
                if (++$count == count($try) and isset($this->controller->fallback))
                    call_user_func_array(array($this->controller, "display"), $this->controller->fallback);
            }

            if ($this->success and !in_array($this->action, array("login", "register")))
                $_SESSION['redirect_to'] = self_url();

            $trigger->call("route_done", $this);

            return true;
        }

        /**
         * Function: url
         * Constructs a canonical URL, translating clean to dirty URLs as necessary.
         *
         * The applicable URL translations are filtered through the @parse_urls@ trigger.
         *
         * Parameters:
         *     $url - The clean URL. Assumed to be dirty if it begins with "/".
         *     $controller - The controller to use. If omitted the current controller will be used.
         *
         * Returns:
         *     An absolute clean or dirty URL, depending on @Config->clean_urls@.
         */
        public function url($url, $controller = null) {
            $config = Config::current();

            fallback($controller, $this->controller);

            if (is_string($controller))
                $controller = $controller::current();

            $base = !empty($controller->base) ? $config->chyrp_url."/".$controller->base : $config->url ;

            # Assume this is a dirty URL and return it without translation.
            if (strpos($url, "/") === 0)
                return $base.$url;

            # Assume this is a clean URL and ensure it ends with a slash.
            $url = rtrim($url, "/")."/";

            # Translation is unnecessary if clean URLs are enabled.
            if ($config->clean_urls and !empty($controller->clean))
                return $base."/".$url;

            $urls = fallback($controller->urls, array());

            Trigger::current()->filter($urls, "parse_urls");

            # Generate a feed variant of all dirty translations not native to the controller.
            foreach (array_diff_assoc($urls, $controller->urls) as $key => $value) {
                $delimiter = substr($key, 0, 1);
                $urls[substr($key, 0, -1).preg_quote("feed/", $delimiter).$delimiter] = $value."&amp;feed";
            }

            # Add a fallback for single parameter translations.
            $urls['|/([^/]+)/$|'] = '/?action=$1';

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
                trigger_error(__("Route was initiated without a Controller."), E_USER_WARNING);

            $instance = (empty($instance)) ? new self($controller) : $instance ;
            return $instance;
        }
    }

