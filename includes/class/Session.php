<?php
    /**
     * Class: Session
     * Handles their session.
     */
    class Session {
        # Variable: $data
        # Caches session data.
        static $data = "";

        # Boolean: $deny
        # Deny this session?
        static $deny = false;

        /**
         * Function: open
         * Decides if the session should be denied.
         */
        static function open() {
            self::$deny = (isset($_SERVER['HTTP_USER_AGENT']) and
                           preg_match("/(bot|crawler|slurp|spider)\b/i", $_SERVER['HTTP_USER_AGENT']));

            return true;
        }

        /**
         * Function: close
         * Returns: @true@
         */
        static function close() {
            return true;
        }

        /**
         * Function: read
         * Reads their session from the database.
         *
         * Parameters:
         *     $id - Session ID.
         */
        static function read($id) {
            self::$data = SQL::current()->select("sessions",
                                                 "data",
                                                 array("id" => $id),
                                                 "id")->fetchColumn();

            return fallback(self::$data, "");
        }

        /**
         * Function: write
         * Writes their session to the database.
         *
         * Parameters:
         *     $id - Session ID.
         *     $data - Data to write.
         */
        static function write($id, $data) {
            if (!self::$deny and !empty($data) and $data != self::$data)
                SQL::current()->replace("sessions",
                                        array("id"),
                                        array("id" => $id,
                                              "data" => $data,
                                              "user_id" => Visitor::current()->id,
                                              "updated_at" => datetime()));

            return true;
        }

        /**
         * Function: destroy
         * Destroys their session.
         *
         * Parameters:
         *     $id - Session ID.
         */
        static function destroy($id) {
            SQL::current()->delete("sessions", array("id" => $id));
            return true;
        }

        /**
         * Function: gc
         * Garbage collector. Removes sessions older than 30 days and sessions with no stored data.
         */
        static function gc() {
            SQL::current()->delete("sessions",
                                   "created_at <= :thirty_days OR data = '' OR data IS NULL",
                                   array(":thirty_days" => datetime(strtotime("-30 days"))));

            return true;
        }
    }
