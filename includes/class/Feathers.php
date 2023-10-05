<?php
    /**
     * Class: Feathers
     * Contains various functions, acts as the backbone for all feathers.
     */
    class Feathers {
        # Array: $instances
        # Holds all feather instantiations.
        static $instances = array();

        # Boolean: $cancelled
        # Is the feather's execution cancelled?
        public $cancelled = false;

        # String: $safename
        # The feather's non-camelized name.
        public $safename = "";

        # Array: $fields
        # The attribute fields for the feather.
        public $fields = array();

        # Array: $filters
        # Manages named trigger filters for feather fields.
        static $filters = array();

        # Array: $custom_filters
        # Manages custom feather-provided trigger filters.
        static $custom_filters = array();

        /**
         * Function: setFilter
         * Applies a filter to a specified field of the Feather.
         *
         * Parameters:
         *     $field - Attribute of the post to filter.
         *     $name - Name of the filter to use.
         *
         * See Also:
         *     <Trigger.filter>
         */
        protected function setFilter($field, $name): void {
            self::$filters[get_class($this)][] = array("field" => $field, "name" => $name);

            if (isset($this->fields[$field])) {
                foreach ((array) $name as $filter)
                    $this->fields[$field]["filters"][] = $filter;
            }
        }

        /**
         * Function: customFilter
         * Allows a Feather to apply its own filter to a specified field.
         *
         * Parameters:
         *     $field - Attribute of the post to filter.
         *     $name - Name of the class function to use as the filter.
         *
         * See Also:
         *     <Trigger.filter>
         */
        protected function customFilter($field, $name): void {
            self::$custom_filters[get_class($this)][] = array("field" => $field, "name" => $name);

            if (isset($this->fields[$field])) {
                foreach ((array) $name as $filter)
                    $this->fields[$field]["custom_filters"][] = $filter;
            }
        }

        /**
         * Function: respondTo
         * Allows a Feather to respond to a Trigger as a Module would.
         *
         * Parameters:
         *     $name - Name of the trigger to respond to.
         *     $function - Name of the class function to respond with.
         *     $priority - Priority of the response.
         *
         * See Also:
         *     <Trigger>
         */
        protected function respondTo($name, $function = null, $priority = 10): void {
            fallback($function, $name);
            Trigger::current()->priorities[$name][] = array(
                "priority" => $priority,
                "function" => array($this, $function)
            );
        }

        /**
         * Function: setField
         * Sets the feather's fields for creating/editing posts with that feather.
         *
         * Parameters:
         *     $options - An array of key => val options for the field.
         *
         * Options:
         *     attr - The technical name for the field. Think $post->attr.
         *     type - The field type. (text, file, text_block, or select)
         *     label - The label for the field.
         *     optional - Is this field optional?
         *     extra - Stuff to output after the input field. Can be anything.
         *     note - A minor note to display next to the label text.
         */
        protected function setField($options): void {
            $this->fields[$options["attr"]] = $options;
        }
    }
