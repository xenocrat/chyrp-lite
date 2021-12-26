<?php
    /**
     * Class: Trigger
     * Controls and keeps track of all of the Triggers and events.
     */
    class Trigger {
        # Array: $priorities
        # Custom prioritized callbacks.
        public $priorities = array();

        # Array: $called
        # Keeps track of called Triggers.
        private $called = array();

        # Array: $exists
        # Caches trigger exist states.
        private $exists = array();

        /**
         * Function: __construct
         * Add predefined filters to implement Unicode emoji and Markdown support.
         */
        private function __construct() {
            $config = Config::current();

            if ($config->enable_emoji)
                $this->priorities["markup_text"][] = array("priority" => 10, "function" => "emote");   

            if ($config->enable_markdown)
                $this->priorities["markup_text"][] = array("priority" => 5, "function" => "markdown"); 
        }

        /**
         * Function: cmp
         * Sorts actions by priority when used with usort.
         */
        private function cmp($a, $b) {
            if (empty($a) or empty($b))
                return 0;

            return ($a["priority"] < $b["priority"]) ? -1 : 1 ;
        }

        /**
         * Function: call
         * Calls a trigger action.
         *
         * Parameters:
         *     $name - The name of the trigger, or an array of triggers to call.
         *
         * Notes:
         *     Any additional arguments are passed on to the functions being called.
         */
        public function call($name) {
            $return = false;

            if (is_array($name)) {
                foreach ($name as $call) {
                    $args = func_get_args();
                    $args[0] = $call;

                    $success = call_user_func_array(array($this, "call"), $args);

                    if ($success !== false)
                        $return = $success;
                }

                return $return;
            }

            if (!$this->exists($name))
                return $return;

            $arguments = func_get_args();
            array_shift($arguments);
            $this->called[$name] = array();

            if (isset($this->priorities[$name]) and usort($this->priorities[$name], array($this, "cmp")))
                foreach ($this->priorities[$name] as $action) {
                    $function = $action["function"];

                    if (is_array($function)) {
                        $object = $function[0];

                        if (is_object($object) and !empty($object->cancelled))
                            continue;
                    }

                    $return = call_user_func_array($function, $arguments);
                    $this->called[$name][] = $function;
                }

            foreach (Modules::$instances as $module)
                if (!in_array(array($module, $name), $this->called[$name]) and is_callable(array($module, $name))) {
                    if (!empty($module->cancelled))
                        continue;

                    $return = call_user_func_array(array($module, $name), $arguments);
                }

            return $return;
        }

        /**
         * Function: filter
         * Modify a variable by filtering it through a stackable set of trigger actions.
         *
         * Parameters:
         *     &$target - The variable to filter.
         *     $name - The name of the trigger.
         *
         * Returns:
         *     $target, filtered through any/all actions for the trigger $name.
         *
         * Notes:
         *     Any additional arguments are passed on to the functions being called.
         */
        public function filter(&$target, $name) {
            if (is_array($name)) {
                foreach ($name as $filter) {
                    $args = func_get_args();
                    $args[0] =& $target;
                    $args[1] = $filter;

                    $target = call_user_func_array(array($this, "filter"), $args);
                }

                return $target;
            }

            if (!$this->exists($name))
                return $target;

            $arguments = func_get_args();
            array_shift($arguments);
            array_shift($arguments);

            $this->called[$name] = array();

            if (isset($this->priorities[$name]) and usort($this->priorities[$name], array($this, "cmp")))
                foreach ($this->priorities[$name] as $action) {
                    $function = $action["function"];

                    if (is_array($function)) {
                        $object = $function[0];

                        if (is_object($object) and !empty($object->cancelled))
                            continue;
                    }

                    $call = call_user_func_array($function,
                                                 array_merge(array(&$target),
                                                                    $arguments));

                    $this->called[$name][] = $function;
                    $target = fallback($call, $target);
                }

            foreach (Modules::$instances as $module)
                if (!in_array(array($module, $name), $this->called[$name]) and is_callable(array($module, $name))) {
                    if (!empty($module->cancelled))
                        continue;

                    $call = call_user_func_array(array($module, $name),
                                                 array_merge(array(&$target),
                                                                    $arguments));

                    $target = fallback($call, $target);
                }

            return $target;
        }

        /**
         * Function: remove
         * Unregisters a given $action from a $trigger.
         *
         * Parameters:
         *     $trigger - The trigger to unregister from.
         *     $action - The action name.
         */
        public function remove($trigger, $action) {
            foreach ($this->actions[$trigger] as $index => $func) {
                if ($func == $action) {
                    unset($this->actions[$trigger][$key]);
                    return;
                }
            }

            $this->actions[$trigger]["disabled"][] = $action;
        }

        /**
         * Function: exists
         * Checks if there are any actions for a given $trigger.
         *
         * Parameters:
         *     $trigger - The trigger name.
         *
         * Returns:
         *     @true@ or @false@
         */
        public function exists($name) {
            if (isset($this->exists[$name]))
                return $this->exists[$name];

            foreach (Modules::$instances as $module)
                if (is_callable(array($module, $name)))
                    return $this->exists[$name] = true;

            if (isset($this->priorities[$name]))
                return $this->exists[$name] = true;

            return $this->exists[$name] = false;
        }

        /**
         * Function: current
         * Returns a singleton reference to the current class.
         */
        public static function & current(): self {
            static $instance = null;
            $instance = (empty($instance)) ? new self() : $instance ;
            return $instance;
        }
    }
