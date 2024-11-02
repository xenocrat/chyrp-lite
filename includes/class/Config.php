<?php
    /**
     * Class: Config
     * Holds all of the configuration settings for the entire site.
     */
    class Config {
        # Array: $data
        # Holds the configuration data as a $key => $val array.
        private $data = array();

        /**
         * Function: __construct
         * Loads the configuration file from disk.
         */
        private function __construct() {
            if (!$this->read() and !INSTALLING)
                trigger_error(
                    __("Could not read the configuration file."),
                    E_USER_ERROR
                );

            fallback($this->data["sql"],              array());
            fallback($this->data["enabled_modules"],  array());
            fallback($this->data["enabled_feathers"], array());
            fallback($this->data["routes"],           array());
        }

        /**
         * Function: __get
         * Handles access to the configuration data.
         *
         * Returns:
         *     @mixed@
         */
        public function __get(
            $name
        ): mixed {
            if (isset($this->data[$name]))
                return $this->data[$name];

            trigger_error(
                __("Requested configuration setting not found."),
                E_USER_NOTICE
            );

            return null;
        }

        /**
         * Function: __isset
         * Handles access to the configuration data.
         */
        public function __isset(
            $name
        ): bool {
            return isset($this->data[$name]);
        }

        /**
         * Function: read
         * Reads the configuration file and decodes the settings.
         */
        private function read(): array|false {
            $security = "<?php header(\"Status: 403\"); exit(\"Access denied.\"); ?>\n";

            $contents = @file_get_contents(
                INCLUDES_DIR.DIR."config.json.php"
            );

            if ($contents === false)
                return false;

            $json = json_get(
                str_replace($security, "", $contents),
                true
            );

            if (!is_array($json))
                return false;

            return $this->data = $json;
        }

        /**
         * Function: write
         * Encodes the settings and writes the configuration file.
         */
        private function write(): int|false {
            $contents = "<?php header(\"Status: 403\"); exit(\"Access denied.\"); ?>\n";

            $contents.= json_set(
                $this->data,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
            );

            return @file_put_contents(
                INCLUDES_DIR.DIR."config.json.php",
                $contents
            );
        }

        /**
         * Function: set
         * Adds or replaces a configuration setting with the given value.
         *
         * Parameters:
         *     $setting - The setting name.
         *     $value - The value to set.
         *     $fallback - Add the setting only if it doesn't exist.
         */
        public function set(
            $setting,
            $value,
            $fallback = false
        ): int|bool {
            if (isset($this->data[$setting]) and $fallback)
                return true;

            $this->data[$setting] = $value;

            if (class_exists("Trigger"))
                Trigger::current()->call("change_setting", $setting, $value);

            return $this->write();
        }

        /**
         * Function: remove
         * Removes a configuration setting.
         *
         * Parameters:
         *     $setting - The setting name.
         */
        public function remove(
            $setting
        ): int|false {
            unset($this->data[$setting]);
            return $this->write();
        }

        /**
         * Function: current
         * Returns a singleton reference to the current configuration.
         */
        public static function & current(): self {
            static $instance = null;
            $instance = (empty($instance)) ? new self() : $instance ;
            return $instance;
        }
    }
