<?php
    /**
     * Class: Config
     * Holds all of the configuration variables for the entire site.
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
            if (!is_readable(INCLUDES_DIR.DIR."config.json.php"))
                return (INSTALLING) ?
                    false :
                    trigger_error(__("The configuration file is not readable."), E_USER_WARNING) ;

            $contents = str_replace("<?php header(\"Status: 403\"); exit(\"Access denied.\"); ?>\n",
                                    "",
                                    file_get_contents(INCLUDES_DIR.DIR."config.json.php"));

            $this->json = json_get($contents, true);

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
         *     $overwrite - Overwrite the setting if it exists and has the same value.
         *     $fallback - Add the setting only if it doesn't already exist.
         */
        public function set($setting, $value, $overwrite = true, $fallback = false) {
            if (isset($this->$setting) and ((!$overwrite and $this->$setting == $value) or $fallback))
                return false;

            # Add the setting.
            $this->json[$setting] = $this->$setting = $value;

            if (class_exists("Trigger"))
                Trigger::current()->call("change_setting", $setting, $value, $overwrite);

            # Add the PHP protection!
            $contents = "<?php header(\"Status: 403\"); exit(\"Access denied.\"); ?>\n";

            # Generate the new JSON settings.
            $contents.= json_set($this->json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

            # Update the configuration file.
            if (!@file_put_contents(INCLUDES_DIR.DIR."config.json.php", $contents))
                trigger_error(__("The configuration file is not writable."), E_USER_WARNING);

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
            # Remove the setting.
            unset($this->json[$setting]);

            # Add the PHP protection!
            $contents = "<?php header(\"Status: 403\"); exit(\"Access denied.\"); ?>\n";

            # Generate the new JSON settings.
            $contents.= json_set($this->json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

            # Update the configuration file.
            if (!@file_put_contents(INCLUDES_DIR.DIR."config.json.php", $contents))
                trigger_error(__("The configuration file is not writable."), E_USER_WARNING);

            return true;
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
