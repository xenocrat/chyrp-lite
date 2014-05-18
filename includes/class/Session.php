<?php
    /**
     * Class: Session
     * Handles their session.
     */
    class Session {
        # Variable: $data
        # Caches session data.
        static $data = "";

        /**
         * Function: open
         * Returns: @true@
         */
        static function open() {
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
         * Writes their session to the database, or updates it if it already exists.
         *
         * Parameters:
         *     $id - Session ID.
         *     $data - Data to write.
         */
        static function write($id, $data) {
            if (empty($data) or $data == self::$data)
                return;

            $sql = SQL::current();

            if ($sql->count("sessions", array("id" => $id)))
                $sql->update("sessions",
                             array("id" => $id),
                             array("data" => $data,
                                   "user_id" => Visitor::current()->id,
                                   "updated_at" => datetime()));
            else
                $sql->insert("sessions",
                             array("id" => $id,
                                   "data" => $data,
                                   "user_id" => Visitor::current()->id,
                                   "created_at" => datetime()));
        }

        /**
         * Function: destroy
         * Destroys their session.
         *
         * Parameters:
         *     $id - Session ID.
         */
        static function destroy($id) {
            if (SQL::current()->delete("sessions", array("id" => $id)))
                return true;

            return false;
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
