<?php
    /**
     * Class: Trigger
     * Controls and keeps track of all of the Triggers and events.
     */
    class Trigger {
        # Array: $priorities
        # Custom prioritized callbacks.
        private $priorities = array();

        # Array: $called
        # Keeps track of called Triggers.
        private $called = array();

        /**
         * Function: __construct
         * Add predefined filters to implement Unicode emoji and Markdown support.
         */
        private function __construct() {
            $config = Config::current();

            if ($config->enable_emoji)
                $this->add("markup_text", 10, "emote");

            if ($config->enable_markdown)
                $this->add("markup_text", 5, "markdown");
        }

        /**
         * Function: add
         * Adds a trigger responder.
         *
         * Parameters:
         *     $name - Name of the trigger to respond to.
         *     $priority - Priority of the response.
         *     $callable - The callable to respond with.
         *
         * Returns:
         *     @true@ or @false@
         */
        public function add(
            $name,
            $priority,
            $callable
        ): bool {
            if (!is_callable($callable))
                return false;

            if (!is_array($callable) and !is_string($callable))
                return false;

            $this->priorities[$name][] = array(
                "priority" => $priority,
                "function" => $callable
            );

            return true;
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
                        $return = $this->postprocess($return, $val);
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
                usort($this->priorities[$name], array($this, "prioritize"))
            ) {
                foreach ($this->priorities[$name] as $action) {
                    $function = $action["function"];

                    if (is_array($function)) {
                        if (
                            $function[0] instanceof Modules and
                            $function[0]->status != Modules::STATUS_ENABLED
                        ) {
                            continue;
                        }

                        if (
                            $function[0] instanceof Feathers and
                            $function[0]->status != Feathers::STATUS_ENABLED
                        ) {
                            continue;
                        }
                    }

                    $val = call_user_func_array($function, $arguments);
                    $return = $this->postprocess($return, $val);

                    $this->called[$name][] = $function;
                }
            }

            foreach (Modules::$instances as $module) {
                if (
                    is_callable(array($module, $name)) and
                    !in_array(array($module, $name), $this->called[$name])
                ) {
                    if ($module->status != Modules::STATUS_ENABLED)
                        continue;

                    $val = call_user_func_array(
                        array($module, $name),
                        $arguments
                    );

                    $return = $this->postprocess($return, $val);
                }
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

            if (
                isset($this->priorities[$name]) and
                usort($this->priorities[$name], array($this, "prioritize"))
            ) {
                foreach ($this->priorities[$name] as $action) {
                    $function = $action["function"];

                    if (is_array($function)) {
                        if (
                            $function[0] instanceof Modules and
                            $function[0]->status != Modules::STATUS_ENABLED
                        ) {
                            continue;
                        }

                        if (
                            $function[0] instanceof Feathers and
                            $function[0]->status != Feathers::STATUS_ENABLED
                        ) {
                            continue;
                        }
                    }

                    $val = call_user_func_array(
                        $function,
                        array_merge(array(&$target), $arguments)
                    );

                    $this->called[$name][] = $function;
                    $target = fallback($val, $target);
                }
            }

            foreach (Modules::$instances as $module) {
                if (
                    is_callable(array($module, $name)) and
                    !in_array(array($module, $name), $this->called[$name])
                ) {
                    if ($module->status != Modules::STATUS_ENABLED)
                        continue;

                    $val = call_user_func_array(
                        array($module, $name),
                        array_merge(array(&$target), $arguments)
                    );

                    $target = fallback($val, $target);
                }
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
            foreach (Modules::$instances as $module) {
                if (is_callable(array($module, $name))) {
                    if ($module->status != Modules::STATUS_ENABLED)
                        continue;

                    return true;
                }
            }

            if (isset($this->priorities[$name])) {
                foreach ($this->priorities[$name] as $action) {
                    $function = $action["function"];

                    if (is_array($function)) {
                        if (
                            $function[0] instanceof Modules and
                            $function[0]->status != Modules::STATUS_ENABLED
                        ) {
                            continue;
                        }

                        if (
                            $function[0] instanceof Feathers and
                            $function[0]->status != Feathers::STATUS_ENABLED
                        ) {
                            continue;
                        }
                    }

                    return true;
                }
            }

            return false;
        }

        /**
         * Function: prioritize
         * Sorts actions by priority when used with usort.
         */
        private function prioritize(
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
         * Function: postprocess
         * Post-processes call return values.
         */
        private function postprocess(
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
         * Function: current
         * Returns a singleton reference to the current class.
         */
        public static function & current(
        ): self {
            static $instance = null;
            $instance = (empty($instance)) ? new self() : $instance ;
            return $instance;
        }
    }
