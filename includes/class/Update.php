<?php
    /**
     * Class: Update
     * Manages Chyrp update process.
     */
    class Update {
        /**
         * Function: xml
         * Loads the update XML file.
         */
        private static function xml() {
            $xml = simplexml_load_string(get_remote("http://chyrp.net/update.xml"));
            return $xml;
        }

        /**
         * Function: check_update
         * Checks if the a new version of Chyrp is available.
         */
        public static function check_update() {
            if (!Config::current()->check_updates)
                return;

            $xml = self::xml();
            $curver = CHYRP_VERSION;

            foreach ($xml->channel->item as $item) {
                $newver = $item->version;

                if (version_compare($curver, $newver, ">="))
                    $return = false;
                else {
                    $return = _f("<p class='message'>Chyrp v%s is available, you have v%s. <a href='?action=update'>Learn More</a></p>", array($newver, $curver));
                    break;
                }
            }

            return $return;
        }

        /**
         * Function: update_url
         * Returns the update URL.
         */
        public static function update_url() {
            $xml = self::xml();
            $curver = CHYRP_VERSION;

            foreach ($xml->channel->item as $item) {
                $newver = $item->version;

                if (version_compare($curver, $newver, ">=")) {
                    $return = false;
                } else {
                    $return = $item->updateurl;
                    break;
                }
            }

            return $return;
        }

        /**
         * Function: get_changelog
         * Returns the changelog for the new available update.
         */
        public static function get_changelog() {
            $xml = self::xml();
            $curver = CHYRP_VERSION;

            foreach ($xml->channel->item as $item) {
                $newver = $item->version;

                if (!version_compare($curver, $newver, ">=")) {
                    $version = "Chyrp v".$item->version;
                    $changelog = $item->changelog;
                    $updateurl = $item->updateurl;
                    $downloadurl = $item->downloadurl;
                    break;
                }
            }

            $return = "<h2>$version</h2>";
            $return.= "<h3>This update includes the following:</h3>";
            $return.= "<p>$changelog</p>";
            $return.= "<p class='buttons'><a href='$downloadurl' class='button yay'>Download from Github</a>";
            $return.= '<a href="?action=update&get_update" class="button yay">Update Automatically</a></p>';

            return $return;
        }

        /**
         * Function: get_update
         * Grabs update off the server.
         */
        public static function get_update() {
            if (self::check_update() == false)
                $message = __("<h2>Chyrp is up to date!</h2>");
            else {
                $ch = curl_init(self::update_url());
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
                curl_setopt($ch, CURLOPT_TIMEOUT, 50);
                $filedata = curl_exec($ch);
                curl_close($ch);
                file_put_contents(MAIN_DIR."/update.zip", $filedata);
                
                if (file_exists(MAIN_DIR."/update.zip"))
                    $message = __("<h2>File downloaded!</h2>");

                if (function_exists("zip_open")) {
                    $zip = new ZipArchive;

                    if ($zip->open(MAIN_DIR."/update.zip") === true) {
                        $zip->extractTo(MAIN_DIR);
                        $zip->close();
                        include MAIN_DIR."/update/updater.php";
                    } else
                        $message = __("<h2>Something Went Wrong!</h2>");
                }
            }

            return $message;
        }
    }
