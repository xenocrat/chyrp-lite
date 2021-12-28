<?php
    /**
     * Class: Session
     * Handles visitor sessions.
     */
    class Session implements SessionHandlerInterface {
        # Variable: $data
        # Caches session data.
        private $data = null;

        # Variable: $created_at
        # Session creation date.
        private $created_at = null;

        # Boolean: $deny
        # Deny session storage?
        private $deny = false;

        /**
         * Function: open
         * Opens the session and decides if session storage will be denied.
         *
         * Parameters:
         *     $path - Filesystem path.
         *     $name - The session name.
         */
        public function open($path, $name): bool {
            $this->created_at = datetime();

            if (SESSION_DENY_BOT and BOT_UA)
                    $this->deny = true;

            if (SESSION_DENY_RPC and XML_RPC)
                $this->deny = true;

            return true;
        }

        /**
         * Function: close
         * Executed when the session is closed.
         */
        public function close(): bool {
            return true;
        }

        /**
         * Function: read
         * Reads a session from the database.
         *
         * Parameters:
         *     $id - Session ID.
         */
        #[\ReturnTypeWillChange]
        public function read($id) {
            $result = SQL::current()->select("sessions",
                                             array("data", "created_at"),
                                             array("id" => $id))->fetch();

            if (!empty($result)) {
                $this->data = $result["data"];
                $this->created_at = $result["created_at"];
            }

            return isset($this->data) ? $this->data : "" ;
        }

        /**
         * Function: write
         * Writes a session to the database.
         *
         * Parameters:
         *     $id - Session ID.
         *     $data - Data to write.
         */
        public function write($id, $data): bool {
            $sql = SQL::current();
            $visitor = Visitor::current();

            if (!$this->deny and isset($data) and $data != $this->data)
                $sql->replace("sessions",
                              array("id"),
                              array("id" => $id,
                                    "data" => $data,
                                    "user_id" => $visitor->id,
                                    "created_at" => $this->created_at,
                                    "updated_at" => datetime()));

            return true;
        }

        /**
         * Function: destroy
         * Deletes a session from the database.
         *
         * Parameters:
         *     $id - Session ID.
         */
        public function destroy($id): bool {
            SQL::current()->delete("sessions", array("id" => $id));
            return true;
        }

        /**
         * Function: gc
         * Deletes sessions not updated for 30+ days, or with no stored data.
         *
         * Parameters:
         *     $lifetime - The configured maximum session lifetime in seconds.
         */
        #[\ReturnTypeWillChange]
        public function gc($lifetime) {
            SQL::current()->delete("sessions",
                                   "updated_at <= :thirty_days OR data = '' OR data IS NULL",
                                   array(":thirty_days" => datetime(strtotime("-30 days"))));

            return true;
        }
    }
