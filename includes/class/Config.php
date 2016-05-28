<?php
    /**
     * Class: Config
     * Holds all of the configuration variables for the entire site, as well as Module settings.
     */
    class Config {
        # Variable: $json
        # Holds all of the JSON settings as a $key => $val array.
        private $json = array();

        /**
         * Function: __construct
         * Loads the configuration JSON file.
         */
        private function __construct() {
            if (!file_exists(INCLUDES_DIR.DIR."config.json.php"))
                return false;

            $contents = str_replace("<?php header(\"Status: 403\"); exit(\"Access denied.\"); ?>\n",
                                    "",
                                    file_get_contents(INCLUDES_DIR.DIR."config.json.php"));

            $this->json = json_decode($contents, true);

            if (json_last_error())
                error(__("Error"),
                      _f("Failed to read configuration file because of JSON error: <code>%s</code>", fix(json_last_error_msg())));

            $arrays = array("enabled_modules", "enabled_feathers", "routes");

            foreach ($this->json as $setting => $value)
                if (in_array($setting, $arrays) and empty($value))
                    $this->$setting = array();
                elseif (!is_int($setting))
                    $this->$setting = (is_string($value)) ? stripslashes($value) : $value ;

            fallback($this->url, $this->chyrp_url);
        }

        /**
         * Function: set
         * Adds or replaces a configuration setting with the given value.
         *
         * Parameters:
         *     $setting - The setting name.
         *     $value - The value.
         *     $overwrite - If the setting exists and is the same value, should it be overwritten?
         */
        public function set($setting, $value, $overwrite = true) {
            if (isset($this->$setting) and $this->$setting == $value and !$overwrite)
                return false;

            # Add the setting
            $this->json[$setting] = $this->$setting = $value;

            if (class_exists("Trigger"))
                Trigger::current()->call("change_setting", $setting, $value, $overwrite);

            # Add the PHP protection!
            $contents = "<?php header(\"Status: 403\"); exit(\"Access denied.\"); ?>\n";

            # Generate the new JSON settings
            $contents.= json_encode($this->json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

            if (json_last_error())
                error(__("Error"),
                      _f("Failed to set <code>%s</code> because of JSON error: <code>%s</code>", array(fix($setting), fix(json_last_error_msg()))));

            # Update the configuration file
            if (!@file_put_contents(INCLUDES_DIR.DIR."config.json.php", $contents))
                error(__("Error"),
                      _f("Failed to set <code>%s</code> because <em>config.json.php</em> is not writable.", fix($setting)));

            return true;
        }

        /**
         * Function: remove
         * Removes a configuration setting.
         *
         * Parameters:
         *     $setting - The name of the setting to remove.
         */
        public function remove($setting) {
            # Remove the setting
            unset($this->json[$setting]);

            # Add the PHP protection!
            $contents = "<?php header(\"Status: 403\"); exit(\"Access denied.\"); ?>\n";

            # Generate the new JSON settings
            $contents.= json_encode($this->json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

            if (json_last_error())
                error(__("Error"),
                      _f("Failed to remove <code>%s</code> because of JSON error: <code>%s</code>", array($setting, fix(json_last_error_msg()))));

            # Update the configuration file
            if (!@file_put_contents(INCLUDES_DIR.DIR."config.json.php", $contents))
                error(__("Error"),
                      _f("Failed to remove <code>%s</code> because <em>config.json.php</em> is not writable.", fix($setting)));
        }

        /**
         * Function: current
         * Returns a singleton reference to the current configuration.
         */
        public static function & current() {
            static $instance = null;
            $instance = (empty($instance)) ? new self() : $instance ;
            return $instance;
        }
    }
