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
         * Function: cmp
         * Sorts actions by priority when used with usort.
         */
        private function cmp($a, $b) {
            if (empty($a) or empty($b)) return 0;
            return ($a["priority"] < $b["priority"]) ? -1 : 1 ;
        }

        /**
         * Function: call
         * Calls a trigger, passing any additional arguments to it.
         *
         * Parameters:
         *     $name - The name of the trigger, or an array of triggers to call.
         */
        public function call($name) {
            if (is_array($name)) {
                $return = null;

                foreach ($name as $index => $call) {
                    $args = func_get_args();
                    $args[0] = $call;
                    if ($index + 1 == count($name))
                        return $this->exists($call) ? call_user_func_array(array($this, "call"), $args) : $return ;
                    else
                        $return = $this->exists($call) ? call_user_func_array(array($this, "call"), $args) : $return ;
                }
            }

            if (!$this->exists($name))
                return false;

            $arguments = func_get_args();
            array_shift($arguments);

            $return = null;

            $this->called[$name] = array();
            if (isset($this->priorities[$name])) { # Predefined priorities?
                usort($this->priorities[$name], array($this, "cmp"));

                foreach ($this->priorities[$name] as $action) {
                    $return = call_user_func_array($action["function"], $arguments);
                    $this->called[$name][] = $action["function"];
                }
            }

            foreach (Modules::$instances as $module)
                if (!in_array(array($module, $name), $this->called[$name]) and is_callable(array($module, $name)))
                    $return = call_user_func_array(array($module, $name), $arguments);

            return $return;
        }

        /**
         * Function: filter
         * Filters a variable through a trigger's actions. Similar to <call>, except this is stackable and is intended to
         * modify something instead of inject code.
         *
         * Any additional arguments passed to this function are passed to the function being called.
         *
         * Parameters:
         *     &$target - The variable to filter.
         *     $name - The name of the trigger.
         *
         * Returns:
         *     $target, filtered through any/all actions for the trigger $name.
         */
        public function filter(&$target, $name) {
            if (is_array($name))
                foreach ($name as $index => $filter) {
                    $args = func_get_args();
                    $args[0] =& $target;
                    $args[1] = $filter;
                    if ($index + 1 == count($name))
                        return $target = call_user_func_array(array($this, "filter"), $args);
                    else
                        $target = call_user_func_array(array($this, "filter"), $args);
                }

            if (!$this->exists($name))
                return $target;

            $arguments = func_get_args();
            array_shift($arguments);
            array_shift($arguments);

            $this->called[$name] = array();

            if (isset($this->priorities[$name]) and usort($this->priorities[$name], array($this, "cmp")))
                foreach ($this->priorities[$name] as $action) {
                    $call = call_user_func_array($this->called[$name][] = $action["function"],
                                                 array_merge(array(&$target), $arguments));
                    $target = fallback($call, $target);
                }

            foreach (Modules::$instances as $module)
                if (!in_array(array($module, $name), $this->called[$name]) and is_callable(array($module, $name))) {
                    $call = call_user_func_array(array($module, $name),
                                                 array_merge(array(&$target), $arguments));
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
        public static function & current() {
            static $instance = null;
            return $instance = (empty($instance)) ? new self() : $instance ;
        }
    }
