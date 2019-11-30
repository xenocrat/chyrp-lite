<?php
   /**
    * Class: Translation
    * A shim for translation support in the absence of GNU gettext.
    */
    class Translation {
        const MO_MAGIC_WORD_BE = "950412de";
        const MO_MAGIC_WORD_LE = "de120495";
        const MO_SIZEOF_HEADER = 28;
        const LONG_LONG = 8;

        # Array: $mo
        # The loaded translations for each domain.
        private $mo = array();

        # String: $locale
        # The current locale.
        private $locale = "en_US";

        /**
         * Function: __construct
         * Discovers the current locale.
         */
        private function __construct() {
            $this->locale = get_locale();
        }

       /**
        * Function: load
        * Loads translations from the .mo file into the supplied domain.
        *
        * Parameters:
        *     $domain - The name of this translation domain.
        *     $path - The path to the locale directory.
        *     $reload - Reload the translation if already loaded?
        */
        public function load($domain, $path, $reload = false) {
            $filepath = $path.DIR.$this->locale.DIR."LC_MESSAGES".DIR.$domain.".mo";

            if (isset($this->mo[$domain]) and !$reload)
                return true;

            if (!is_file($filepath) or !is_readable($filepath))
                return false;

            $mo_file = file_get_contents($filepath);
            $mo_data = array();
            $mo_length = strlen($mo_file);
            $big_endian = null;

            if (self::MO_SIZEOF_HEADER > $mo_length)
                return false;

            $id = unpack("H8magic", $mo_file);

            if ($id["magic"] == self::MO_MAGIC_WORD_BE)
                $big_endian = true;

            if ($id["magic"] == self::MO_MAGIC_WORD_LE)
                $big_endian = false;

            # Neither magic word matches; not a valid .mo file.
            if (!isset($big_endian))
                return false;

            $unpack = ($big_endian) ?
                "Nformat/Nnum/Nor/Ntr" :
                "Vformat/Vnum/Vor/Vtr" ;

            $mo_offset = unpack($unpack, $mo_file, 4);

            $unpack = ($big_endian) ?
                "Nlength/Noffset" :
                "Vlength/Voffset" ;

            for ($i = 0; $i < $mo_offset["num"]; $i++) {
                $or_str_offset = $mo_offset["or"] + ($i * self::LONG_LONG);
                $tr_str_offset = $mo_offset["tr"] + ($i * self::LONG_LONG);

                if (($or_str_offset + self::LONG_LONG) > $mo_length)
                    return false;

                if (($tr_str_offset + self::LONG_LONG) > $mo_length)
                    return false;

                $or_str_meta = unpack($unpack, $mo_file, $or_str_offset);
                $tr_str_meta = unpack($unpack, $mo_file, $tr_str_offset);

                $or_str_end = $or_str_meta["offset"] + $or_str_meta["length"];
                $tr_str_end = $tr_str_meta["offset"] + $tr_str_meta["length"];

                if ($or_str_end > $mo_length)
                    return false;

                if ($tr_str_end > $mo_length)
                    return false;

                $or_str_data = substr($mo_file,
                                      $or_str_meta["offset"],
                                      $or_str_meta["length"]);

                $tr_str_data = substr($mo_file,
                                      $tr_str_meta["offset"],
                                      $tr_str_meta["length"]);

                # Discover msgid null-separated plural forms.
                if (strpos($or_str_data, "\0") !== false) {
                    $or_str_data = explode("\0", $or_str_data);
                    $tr_str_data = explode("\0", $tr_str_data);
                }

                $or_str_data = (array) $or_str_data;
                $tr_str_data = (array) $tr_str_data;

                # Add discovered msgid+msgstr pairs to the data.
                for ($z = 0; $z < count($or_str_data); $z++) {
                    fallback($tr_str_data[$z], "");

                    $mo_data[] = array("or" => $or_str_data[$z],
                                       "tr" => $tr_str_data[$z]);
                }
            }

            $this->mo[$domain] = $mo_data;
            return true;
        }

       /**
        * Function: text
        * Returns the singular or plural translation of a string.
        *
        * Parameters:
        *     $domain - The translation domain to search.
        *     $single - Singular string.
        *     $plural - Pluralized string (optional).
        *     $number - The number to judge by (optional).
        */
        public function text($domain, $single, $plural = null, $number = 1) {
            $single = isset($single) ? $this->find($domain, $single) : "" ;
            $plural = isset($plural) ? $this->find($domain, $plural) : "" ;

            return ((int) $number != 1) ? $plural : $single ;
        }

       /**
        * Function: find
        * Returns a translation string from the supplied domain.
        */
        public function find($domain, $string) {
            if (!isset($this->mo[$domain]))
                return $string;

            foreach ($this->mo[$domain] as $entry) {
                if ($entry["or"] == $string)
                    return oneof($entry["tr"], $string);
            }

            return $string;
        }

        /**
         * Function: current
         * Returns a singleton reference to the current class.
         */
        public static function & current() {
            static $instance = null;
            $instance = (empty($instance)) ? new self() : $instance ;
            return $instance;
        }
    }
