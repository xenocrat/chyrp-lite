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
        public $deny = false;

        # Object: $instance
        # Holds the session instantiation.
        public static $instance = null;

        /**
         * Function: open
         * Opens the session and decides if session storage will be denied.
         *
         * Parameters:
         *     $path - Filesystem path.
         *     $name - The session name.
         */
        public function open(
            $path,
            $name
        ): bool {
            $this->created_at = datetime();
            $this->deny = (SESSION_DENY_BOT and BOT_UA);

            return true;
        }

        /**
         * Function: close
         * Executed when the session is closed.
         */
        public function close(
        ): bool {
            return true;
        }

        /**
         * Function: read
         * Reads a session from the database.
         *
         * Parameters:
         *     $id - Session ID.
         */
        public function read(
            $id
        ): string|false {
            $result = SQL::current()->select(
                tables:"sessions",
                fields:array("data", "created_at"),
                conds:array("id" => $id)
            )->fetch();

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
        public function write(
            $id,
            $data
        ): bool {
            $sql = SQL::current();
            $visitor = Visitor::current();

            if ($this->deny)
                return true;

            if (isset($data) and $data != $this->data) {
                $sql->replace(
                    table:"sessions",
                    keys:array("id"),
                    data:array(
                        "id" => $id,
                        "data" => $data,
                        "user_id" => $visitor->id,
                        "created_at" => $this->created_at,
                        "updated_at" => datetime()
                    )
                );
            }

            return true;
        }

        /**
         * Function: destroy
         * Deletes a session from the database.
         *
         * Parameters:
         *     $id - Session ID.
         */
        public function destroy(
            $id
        ): bool {
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
        public function gc(
            $lifetime
        ): int|false {
            SQL::current()->delete(
                "sessions",
                "updated_at < :expired_cookie OR data = '' OR data IS NULL",
                array(":expired_cookie" => datetime(time() - COOKIE_LIFETIME))
            );

            return true;
        }

        /**
         * Function: hash_token
         * Generates an authentication token for this session.
         */
        public static function hash_token(
        ): bool|string {
            $id = session_id();

            if ($id === "")
                return false;

            return token($id);
        }

        /**
         * Function: check_token
         * Validates an authentication token for this session.
         *
         * Parameters:
         *     $hash - The token to validate.
         */
        public static function check_token(
            $hash
        ): bool {
            $token = self::hash_token();

            if ($token === false)
                return false;

            return hash_equals($token, $hash);
        }
    }
