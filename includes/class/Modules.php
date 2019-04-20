<?php
    /**
     * Class: Modules
     * Contains various functions, acts as the backbone for all modules.
     */
    class Modules {
        # Array: $instances
        # Holds all Module instantiations.
        static $instances = array();

        # Boolean: $cancelled
        # Is the module's execution cancelled?
        public $cancelled = false;

        # String: $safename
        # The module's non-camelized name.
        public $safename = "";

        /**
         * Function: setPriority
         * Sets the priority of an action for the module this function is called from.
         *
         * Parameters:
         *     $name - Name of the trigger to respond to.
         *     $priority - Priority of the response.
         */
        protected function setPriority($name, $priority) {
            Trigger::current()->priorities[$name][] = array("priority" => $priority,
                                                            "function" => array($this, $name));
        }

        /**
         * Function: addAlias
         * Allows a module to respond to a trigger with multiple functions and custom priorities.
         *
         * Parameters:
         *     $name - Name of the trigger to respond to.
         *     $function - Name of the class function to respond with.
         *     $priority - Priority of the response.
         */
        protected function addAlias($name, $function, $priority = 10) {
            Trigger::current()->priorities[$name][] = array("priority" => $priority,
                                                            "function" => array($this, $function));
        }
    }
