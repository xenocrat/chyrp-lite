<?php
    /**
     * Class: Flash
     * Stores messages, notices, and warnings to display to the user after a redirect.
     */
    class Flash {
        const FLASH_MESSAGE = "message";
        const FLASH_NOTICE  = "notice";
        const FLASH_WARNING = "warning";

        # Array: $message
        # Served messages.
        private static $message = array();

        # Array: $notice
        # Served notices.
        private static $notice = array();

        # Array: $warning
        # Served warnings.
        private static $warning = array();

        /**
         * Function: __construct
         * Prepares the structure of session values.
         */
        private function __construct() {
            self::prepare(self::FLASH_MESSAGE);
            self::prepare(self::FLASH_NOTICE);
            self::prepare(self::FLASH_WARNING);
        }

        /**
         * Function: prepare
         * Prepare the structure of a session value.
         */
        private static function prepare(
            $type
        ): void {
            if (
                !isset($_SESSION[$type]) or
                !is_array($_SESSION[$type])
            ) {
                $_SESSION[$type] = array();
            }
        }

        /**
         * Function: message
         * Create a message (neutral).
         *
         * Parameters:
         *     $message - Text of the message.
         *     $redirect_to - URL to redirect to after the message is stored.
         *     $code - Numeric HTTP status code to set.
         */
        public static function message(
            $message,
            $redirect_to = null,
            $code = null
        ): void {
            $trigger = Trigger::current();
            $type = self::FLASH_MESSAGE;
            self::prepare($type);

            $_SESSION[$type][] = $trigger->filter($message, "flash_message", $redirect_to);

            if (DEBUG and !headers_sent())
                header("X-Chyrp-Flash-Messages: ".self::count($type));

            if (isset($redirect_to))
                redirect($redirect_to, $code);
        }

        /**
         * Function: notice
         * Create a notice (positive).
         *
         * Parameters:
         *     $message - Text of the notice.
         *     $redirect_to - URL to redirect to after the notice is stored.
         *     $code - Numeric HTTP status code to set.
         */
        public static function notice(
            $message,
            $redirect_to = null,
            $code = null
        ): void {
            $trigger = Trigger::current();
            $type = self::FLASH_NOTICE;
            self::prepare($type);

            $_SESSION[$type][] = $trigger->filter($message, "flash_notice", $redirect_to);

            if (DEBUG and !headers_sent())
                header("X-Chyrp-Flash-Notices: ".self::count($type));

            if (isset($redirect_to))
                redirect($redirect_to, $code);
        }

        /**
         * Function: warning
         * Create a warning (negative).
         *
         * Parameters:
         *     $message - Text of the warning.
         *     $redirect_to - URL to redirect to after the warning is stored.
         *     $code - Numeric HTTP status code to set.
         */
        public static function warning(
            $message,
            $redirect_to = null,
            $code = null
        ): void {
            $trigger = Trigger::current();
            $type = self::FLASH_WARNING;
            self::prepare($type);

            $_SESSION[$type][] = $trigger->filter($message, "flash_warning", $redirect_to);

            if (DEBUG and !headers_sent())
                header("X-Chyrp-Flash-Warnings: ".self::count($type));

            if (isset($redirect_to))
                redirect($redirect_to, $code);
        }

        /**
         * Function: messages
         * Calls <Flash.serve> "messages".
         */
        public function messages(): array {
            return $this->serve(self::FLASH_MESSAGE);
        }

        /**
         * Function: notices
         * Calls <Flash.serve> "notices".
         */
        public function notices(): array {
            return $this->serve(self::FLASH_NOTICE);
        }

        /**
         * Function: warnings
         * Calls <Flash.serve> "warnings".
         */
        public function warnings(): array {
            return $this->serve(self::FLASH_WARNING);
        }

        /**
         * Function: all
         * Serves an associative array of all flashes.
         *
         * Returns:
         *     An array of every flash available,
         *     in the form of [type => [flashes]].
         */
        public function all(): array {
            return array(
                "messages" => $this->messages(),
                "notices" => $this->notices(),
                "warnings" => $this->warnings()
            );
        }

        /**
         * Function: serve
         * Serves flashes and removes them from the session.
         *
         * Parameters:
         *     $type - Type of flashes to serve.
         *
         * Returns:
         *     An array of flashes of the requested type.
         */
        private function serve(
            $type
        ): array {
            self::prepare($type);

            if (!empty($_SESSION[$type])) {
                $served = array_merge(
                    self::$$type,
                    $_SESSION[$type]
                );

                self::$$type = $served;
                $_SESSION[$type] = array();
            }

            return self::$$type;
        }

        /**
         * Function: exists
         * Checks for the existence of stored flashes.
         *
         * Parameters:
         *     $type - Type to check for (optional).
         */
        public static function exists(
            $type = null
        ): bool {
            switch ($type) {
                case self::FLASH_MESSAGE:
                case self::FLASH_NOTICE:
                case self::FLASH_WARNING:
                    $check = array($type);
                    break;
                case null:
                    $check = array(
                        self::FLASH_MESSAGE,
                        self::FLASH_NOTICE,
                        self::FLASH_WARNING
                    );
                    break;
                default:
                    return false;
            }

            foreach ($check as $type) {
                self::prepare($type);

                if (!empty(self::$$type))
                    return true;

                if (!empty($_SESSION[$type]))
                    return true;
            }

            return false;
        }

        /**
         * Function: count
         * Counts the total number of stored flashes.
         *
         * Parameters:
         *     $type - Type to check for (optional).
         */
        public static function count(
            $type = null
        ): int {
            $total = 0;

            switch ($type) {
                case self::FLASH_MESSAGE:
                case self::FLASH_NOTICE:
                case self::FLASH_WARNING:
                    $count = array($type);
                    break;
                case null:
                    $count = array(
                        self::FLASH_MESSAGE,
                        self::FLASH_NOTICE,
                        self::FLASH_WARNING
                    );
                    break;
                default:
                    return $total;
            }

            foreach ($count as $type) {
                self::prepare($type);

                if (!empty(self::$$type))
                    $total+= count(self::$$type);

                if (!empty($_SESSION[$type]))
                    $total+= count($_SESSION[$type]);
            }

            return $total;
        }

        /**
         * Function: current
         * Returns a singleton reference to the current class.
         */
        public static function & current(): self {
            static $instance = null;
            $instance = (empty($instance)) ? new self() : $instance ;
            return $instance;
        }
    }
