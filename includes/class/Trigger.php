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
        # Caches trigger existence states.
        private $exists = array();

        /**
         * Function: __construct
         * Add predefined filters to implement Unicode emoji and Markdown support.
         */
        private function __construct() {
            $config = Config::current();

            if ($config->enable_emoji)
                $this->priorities["markup_text"][] = array(
                    "priority" => 10,
                    "function" => "emote"
                );   

            if ($config->enable_markdown)
                $this->priorities["markup_text"][] = array(
                    "priority" => 5,
                    "function" => "markdown"
                ); 
        }

        /**
         * Function: cmp
         * Sorts actions by priority when used with usort.
         */
        private function cmp(
            $a,
            $b
        ): int {
            if (empty($a) or empty($b))
                return 0;

            if ($a["priority"] == $b["priority"])
                return 0;

            return ($a["priority"] < $b["priority"]) ? -1 : 1 ;
        }

        /**
         * Function: decide
         * Decides what to do with a call return value.
         */
        private function decide(
            $return,
            $val
        ): mixed {
            if ($return === false)
                return $val;

            if (is_string($return) and is_string($val))
                return $return.$val;

            return oneof($val, $return);
        }

        /**
         * Function: call
         * Calls a trigger action.
         *
         * Parameters:
         *     $name - The name of the trigger, or an array of triggers to call.
         *
         * Returns:
         *     A concatenated string if all calls return a string, or;
         *     @false@ if none of the triggers exist, or;
         *     the most substantial returned value decided by oneof().
         *
         * Notes:
         *     Any additional arguments are passed on to the trigger responders.
         */
        public function call(
            $name
        ): mixed {
            $return = false;

            if (is_array($name)) {
                foreach ($name as $call) {
                    $args = func_get_args();
                    $args[0] = $call;

                    $val = call_user_func_array(
                        array($this, "call"),
                        $args
                    );

                    if ($val !== false)
                        $return = $this->decide($return, $val);
                }

                return $return;
            }

            if (!$this->exists($name))
                return $return;

            $arguments = func_get_args();
            array_shift($arguments);
            $this->called[$name] = array();

            if (
                isset($this->priorities[$name]) and
                usort($this->priorities[$name], array($this, "cmp"))
            )
                foreach ($this->priorities[$name] as $action) {
                    $function = $action["function"];

                    if (is_array($function)) {
                        $object = $function[0];

                        if (is_object($object) and !empty($object->cancelled))
                            continue;
                    }

                    $val = call_user_func_array($function, $arguments);
                    $return = $this->decide($return, $val);

                    $this->called[$name][] = $function;
                }

            foreach (Modules::$instances as $module)
                if (
                    is_callable(array($module, $name)) and
                    !in_array(array($module, $name), $this->called[$name])
                ) {
                    if (!empty($module->cancelled))
                        continue;

                    $val = call_user_func_array(
                        array($module, $name),
                        $arguments
                    );

                    $return = $this->decide($return, $val);
                }

            return $return;
        }

        /**
         * Function: filter
         * Modify a variable by filtering it through a stack of trigger actions.
         *
         * Parameters:
         *     &$target - The variable to filter.
         *     $name - The name of the trigger.
         *
         * Returns:
         *     $target, filtered through any/all actions for the trigger $name.
         *
         * Notes:
         *     Any additional arguments are passed on to the trigger responders.
         */
        public function filter(
            &$target,
            $name
        ): mixed {
            if (is_array($name)) {
                foreach ($name as $filter) {
                    $args = func_get_args();
                    $args[0] =& $target;
                    $args[1] = $filter;

                    $target = call_user_func_array(
                        array($this, "filter"),
                        $args
                    );
                }

                return $target;
            }

            if (!$this->exists($name))
                return $target;

            $arguments = func_get_args();
            array_shift($arguments);
            array_shift($arguments);

            $this->called[$name] = array();

            if (isset($this->priorities[$name]) and
                usort($this->priorities[$name], array($this, "cmp"))
            )
                foreach ($this->priorities[$name] as $action) {
                    $function = $action["function"];

                    if (is_array($function)) {
                        $object = $function[0];

                        if (is_object($object) and !empty($object->cancelled))
                            continue;
                    }

                    $val = call_user_func_array(
                        $function,
                        array_merge(array(&$target), $arguments)
                    );

                    $this->called[$name][] = $function;
                    $target = fallback($val, $target);
                }

            foreach (Modules::$instances as $module)
                if (
                    is_callable(array($module, $name)) and
                    !in_array(array($module, $name), $this->called[$name])
                ) {
                    if (!empty($module->cancelled))
                        continue;

                    $val = call_user_func_array(
                        array($module, $name),
                        array_merge(array(&$target), $arguments)
                    );

                    $target = fallback($val, $target);
                }

            return $target;
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
        public function exists(
            $name
        ): bool {
            if (isset($this->exists[$name]))
                return $this->exists[$name];

            foreach (Modules::$instances as $module) {
                if (is_callable(array($module, $name)))
                    return $this->exists[$name] = true;
            }

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
