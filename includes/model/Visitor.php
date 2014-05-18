<?php
    /**
     * Class: Visitor
     * The model for the currently browsing <User>. Group falls back to whatever group is set as the "Guest Group".
     *
     * See Also:
     *     <User>
     */
    class Visitor extends User {
        # Integer: $id
        # The ID of the currently visiting "user". 0 if not logged in.
        public $id = 0;

        /**
         * Function: __construct
         * Checks if a valid user is logged in.
         */
        public function __construct() {
            if (!empty($_SESSION['user_id']))
                parent::__construct($_SESSION['user_id']);
        }

        /**
         * Function: __get
         * A detour around belongs_to "group" to account for the default Guest group.
         */
        public function __get($name) {
            if (isset($this->$name))
                return $this->$name;
            elseif ($name == "group") {
                if (!isset($this->group_id))
                    return new Group(Config::current()->guest_group);
                elseif (isset($this->group_name))
                    return new Group(null, array("read_from" => array("id" => $this->group_id,
                                                                      "name" => $this->group_name)));
                else {
                    $group = new Group($this->group_id);
                    return ($group->no_results) ? new Group(Config::current()->default_group) : $group ;
                }
            }
        }

        /**
         * Function: find
         * See Also:
         *     <Model::search>
         */
        static function find($options = array(), $options_for_object = array()) {
            fallback($options["order"], "id ASC");
            return parent::search("user", $options, $options_for_object);
        }

        /**
         * Function: group
         * Returns the user's <Group> or the "Guest Group".
         * 
         * !! DEPRECATED AFTER 2.0 !!
         */
        public function group() {
            if (!isset($this->group_id))
                return new Group(Config::current()->guest_group);
            elseif (isset($this->group_name))
                return new Group(null, array("read_from" => array("id" => $this->group_id,
                                                                  "name" => $this->group_name)));
            else {
                $group = new Group($this->group_id);
                return ($group->no_results) ? new Group(Config::current()->default_group) : $group ;
            }
        }

        /**
         * Function: current
         * Returns a singleton reference to the current visitor.
         */
        public static function & current() {
            static $instance = null;
            return $instance = (empty($instance)) ? new self() : $instance ;
        }
    }
