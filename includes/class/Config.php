<?php
    /**
     * Class: Config
     * Holds all of the configuration settings for the entire site.
     */
    class Config {
        # Array: $json
        # Holds all of the JSON settings as a $key => $val array.
        private $json = array();

        /**
         * Function: __construct
         * Loads the configuration JSON file.
         */
        private function __construct() {
            if (!$this->read() and !INSTALLING)
                trigger_error(__("Could not read the configuration file."), E_USER_ERROR);

            foreach ($this->json as $setting => $value) {
                if (is_numeric($setting))
                    continue;

                if ($setting == "json")
                    continue;

                $this->$setting = $value;
            }

            fallback($this->sql,              array());
            fallback($this->enabled_modules,  array());
            fallback($this->enabled_feathers, array());
            fallback($this->routes,           array());
        }

        /**
         * Function: read
         * Reads the configuration file and decodes the settings.
         */
        private function read() {
            $security = "<?php header(\"Status: 403\"); exit(\"Access denied.\"); ?>\n";
            $contents = @file_get_contents(INCLUDES_DIR.DIR."config.json.php");

            if ($contents === false)
                return false;

            $json = json_get(str_replace($security, "", $contents), true);

            if (!is_array($json))
                return false;

            return $this->json = $json;
        }

        /**
         * Function: write
         * Encodes the settings and writes the configuration file.
         */
        private function write() {
            $contents = "<?php header(\"Status: 403\"); exit(\"Access denied.\"); ?>\n";
            $contents.= json_set($this->json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

            return @file_put_contents(INCLUDES_DIR.DIR."config.json.php", $contents);
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
        public function set($setting, $value, $fallback = false) {
            if (is_numeric($setting))
                return false;

            if ($setting == "json")
                return false;

            if (isset($this->$setting) and $fallback)
                return true;

            $this->json[$setting] = $this->$setting = $value;

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
        public function remove($setting) {
            unset($this->json[$setting]);
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
