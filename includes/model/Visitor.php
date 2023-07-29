<?php
    /**
     * Class: Visitor
     * The model for the currently browsing <User>.
     *
     * Notes:
     *     Group falls back to whatever group is set as the "Guest Group".
     *
     * See Also:
     *     <User>
     */
    class Visitor extends User {
        /**
         * Function: __construct
         * Checks if a valid user is logged in.
         */
        private function __construct() {
            # The user ID of the current visitor. 0 if not logged in.
            $this->id = 0;

            if (!empty($_SESSION['user_id']))
                parent::__construct($_SESSION['user_id']);

            Trigger::current()->filter($this, "visitor");
        }

        /**
         * Function: __get
         * A detour around belongs_to "group" to account for the default Guest group.
         */
        public function &__get($name): mixed {
            if ($name == "group") {
                $this->data["group"] = isset($this->group_id) ?
                    new Group($this->group_id) :
                    new Group(Config::current()->guest_group) ;

                return $this->data["group"];
            }

            return parent::__get($name);
        }

        /**
         * Function: find
         *
         * See Also:
         *     <Model::search>
         */
        static function find(
            $options = array(),
            $options_for_object = array()
        ): array {
            fallback($options["order"], "id ASC");
            return parent::search(
                "user",
                $options,
                $options_for_object
            );
        }

        /**
         * Function: logged_in
         * Returns whether or not the visitor is logged in.
         */
        public static function logged_in(): bool {
            return (
                isset(Visitor::current()->id) and
                Visitor::current()->id != 0
            );
        }

        /**
         * Function: log_in
         * Logs in a visitor as a registered user.
         *
         * Parameters:
         *     $user - The <User> to log in.
         *
         * Notes:
         *     A redirect() is required after calling this method.
         */
        public static function log_in($user): bool {
            if ($user->no_results)
                return false;

            $_SESSION['user_id'] = $user->id;
            Trigger::current()->call("user_logged_in", $user);
            return true;
        }

        /**
         * Function: log_out
         * Logs out a registered user.
         * 
         * Notes:
         *     A redirect() is required after calling this method.
         */
        public static function log_out(): void {
            $user = new User($_SESSION['user_id']);
            session_destroy();
            session();
            Trigger::current()->call("user_logged_out", $user);

            if (!headers_sent())
                header('Clear-Site-Data: "storage"');
        }

        /**
         * Function: current
         * Returns a singleton reference to the current visitor.
         */
        public static function & current(): self {
            static $instance = null;
            $instance = (empty($instance)) ? new self() : $instance ;
            return $instance;
        }
    }
