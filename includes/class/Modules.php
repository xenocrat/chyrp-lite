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
         * Sets the priority of a trigger responder method.
         *
         * Parameters:
         *     $name - Name of the trigger to respond to.
         *     $priority - Priority of the trigger response.
         *
         * Returns:
         *     @true@ or @false@
         */
        protected function setPriority(
            $name,
            $priority
        ): bool {
            return Trigger::current()->add(
                $name,
                $priority,
                array($this, $name)
            );
        }

        /**
         * Function: addAlias
         * Aliases a trigger responder to another method name.
         *
         * Parameters:
         *     $name - Name of the trigger to respond to.
         *     $function - Name of the method to respond with.
         *     $priority - Priority of the trigger response.
         *
         * Returns:
         *     @true@ or @false@
         */
        protected function addAlias(
            $name,
            $function,
            $priority = 10
        ): bool {
            return Trigger::current()->add(
                $name,
                $priority,
                array($this, $function)
            );
        }
    }
