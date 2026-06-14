<?php
    /**
     * Class: Modules
     * Contains various functions, acts as the backbone for all modules.
     */
    class Modules {
        const STATUS_DISABLED  = "disabled";
        const STATUS_ENABLED   = "enabled";
        const STATUS_CANCELED  = "canceled";

        # Array: $instances
        # Holds all module instantiations.
        public static $instances = array();

        # String: $status
        # What is the module's status?
        public $status = self::STATUS_DISABLED;

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
        protected function setPriority(
            $name,
            $priority
        ): void {
            Trigger::current()->priorities[$name][] = array(
                "priority" => $priority,
                "function" => array($this, $name)
            );
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
        protected function addAlias(
            $name,
            $function,
            $priority = 10
        ): void {
            Trigger::current()->priorities[$name][] = array(
                "priority" => $priority,
                "function" => array($this, $function)
            );
        }
    }
