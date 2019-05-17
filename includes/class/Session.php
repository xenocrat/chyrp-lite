<?php
    /**
     * Class: Session
     * Handles visitor sessions.
     */
    class Session implements SessionHandlerInterface {
        # Variable: $data
        # Caches session data.
        public $data = "";

        # Boolean: $deny
        # Deny session storage?
        public $deny = false;

        /**
         * Function: open
         * Opens the session and decides if session storage should be denied.
         */
        public function open($path, $name) {
            $this->deny = (isset($_SERVER['HTTP_USER_AGENT']) and
                           preg_match("/(bot|crawler|slurp|spider)\b/i", $_SERVER['HTTP_USER_AGENT']));

            return true;
        }

        /**
         * Function: close
         * Executed when the session is closed.
         */
        public function close() {
            return true;
        }

        /**
         * Function: read
         * Reads a session from the database.
         *
         * Parameters:
         *     $id - Session ID.
         */
        public function read($id) {
            $this->data = SQL::current()->select("sessions",
                                                 "data",
                                                 array("id" => $id),
                                                 "id")->fetchColumn();

            return !empty($this->data) ? $this->data : "" ;
        }

        /**
         * Function: write
         * Writes a session to the database.
         *
         * Parameters:
         *     $id - Session ID.
         *     $data - Data to write.
         */
        public function write($id, $data) {
            if (!$this->deny and !empty($data) and $data != $this->data)
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
         * Destroys a session in the database.
         *
         * Parameters:
         *     $id - Session ID.
         */
        public function destroy($id) {
            SQL::current()->delete("sessions", array("id" => $id));
            return true;
        }

        /**
         * Function: gc
         * Removes sessions older than 30 days and sessions with no stored data.
         */
        public function gc($lifetime) {
            SQL::current()->delete("sessions",
                                   "created_at <= :thirty_days OR data = '' OR data IS NULL",
                                   array(":thirty_days" => datetime(strtotime("-30 days"))));

            return true;
        }
    }
