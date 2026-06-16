<?php
    /**
     * Class: Feathers
     * Contains various functions, acts as the backbone for all feathers.
     */
    class Feathers {
        const STATUS_DISABLED  = "disabled";
        const STATUS_ENABLED   = "enabled";
        const STATUS_CANCELED  = "canceled";

        # Array: $instances
        # Holds all feather instantiations.
        public static $instances = array();

        # String: $status
        # What is the feather's status?
        public $status = self::STATUS_DISABLED;

        # String: $safename
        # The feather's non-camelized name.
        public $safename = "";

        # Array: $fields
        # The attribute fields for the feather.
        public $fields = array();

        # Array: $filters
        # Manages named trigger filters for feather fields.
        private static $filters = array();

        # Array: $custom_filters
        # Manages custom feather-provided trigger filters.
        private static $custom_filters = array();

        /**
         * Function: filter
         * Applies feather filters to a post.
         *
         * Parameters:
         *     $post - The post object to filter.
         *
         * See Also:
         *     <Post.filter>
         *     <Feathers.filterField>
         */
        public static function filter(
            $post
        ): Post|false {
            if ($post->no_results)
                return false;

            $trigger = Trigger::current();
            $touched = array();
            $class_name = camelize($post->feather);

            # Custom filters.
            if (isset(self::$custom_filters[$class_name])) {
                foreach (self::$custom_filters[$class_name] as $custom_filter) {
                    $field = $custom_filter["field"];
                    $field_unfiltered = $field."_unfiltered";

                    if (!in_array($field_unfiltered, $touched)) {
                        $post->$field_unfiltered = $post->$field ?? null ;
                        $touched[] = $field_unfiltered;
                    }

                    $post->$field = call_user_func_array(
                        array(
                            self::$instances[$post->feather],
                            $custom_filter["name"]
                        ),
                        array($post->$field, $post)
                    );
                }
            }

            # Trigger filters.
            if (isset(self::$filters[$class_name])) {
                foreach (self::$filters[$class_name] as $filter) {
                    $field = $filter["field"];
                    $field_unfiltered = $field."_unfiltered";

                    if (!in_array($field_unfiltered, $touched)) {
                        $post->$field_unfiltered = $post->$field ?? null ;
                        $touched[] = $field_unfiltered;
                    }

                    $trigger->filter($post->$field, $filter["name"], $post);
                }
            }

            return $post;
        }

        /**
         * Function: setField
         * Defines a feather field to be used for post writing and editing.
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
        protected function setField(
            $options
        ): void {
            $this->fields[$options["attr"]] = $options;
        }

        /**
         * Function: setFilter
         * Applies a filter to a specified field of the feather.
         *
         * Parameters:
         *     $field - Attribute of the post to filter.
         *     $name - Name of the filter to use.
         *
         * Returns:
         *     @true@ or @false@
         *
         * See Also:
         *     <Trigger.filter>
         */
        protected function setFilter(
            $field,
            $name
        ): bool {
            if (!isset($this->fields[$field]))
                return false;

            self::$filters[get_class($this)][] = array(
                "field" => $field,
                "name" => $name
            );

            foreach ((array) $name as $filter)
                $this->fields[$field]["filters"][] = $filter;

            return true;
        }

        /**
         * Function: customFilter
         * Allows a Feather to apply its own filter to a specified field.
         *
         * Parameters:
         *     $field - Attribute of the post to filter.
         *     $name - Name of the class function to use as the filter.
         *
         * Returns:
         *     @true@ or @false@
         *
         * See Also:
         *     <Trigger.filter>
         */
        protected function customFilter(
            $field,
            $name
        ): bool {
            if (!isset($this->fields[$field]))
                return false;

            self::$custom_filters[get_class($this)][] = array(
                "field" => $field,
                "name" => $name
            );

            foreach ((array) $name as $filter)
                $this->fields[$field]["custom_filters"][] = $filter;

            return true;
        }

        /**
         * Function: respondTo
         * Allows a Feather to respond to a trigger as a module would.
         *
         * Parameters:
         *     $name - Name of the trigger to respond to.
         *     $function - Name of the class function to respond with.
         *     $priority - Priority of the response.
         *
         * Returns:
         *     @true@ or @false@
         *
         * See Also:
         *     <Trigger>
         */
        protected function respondTo(
            $name,
            $function = null,
            $priority = 10
        ): bool {
            fallback($function, $name);

            return Trigger::current()->add(
                $name,
                $priority,
                array($this, $function)
            );
        }
    }
