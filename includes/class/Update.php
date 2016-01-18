<?php
    /**
     * Class: Update
     * Handles updates to Chyrp Lite.
     */
    class Update {
        /**
         * Function: check
         * Checks if a newer version of Chyrp Lite is available.
         */
        public static function check() {
            $xml = simplexml_load_string(get_remote(UPDATE_XML, 3));
            Config::current()->set("check_updates_last", time());

            if ($xml == false) {
                Flash::warning(__("Unable to check for updates.").
                                  ' <a href="'.UPDATE_PAGE.'" target="_blank">'.__("Go to GitHub &rarr;").'</a>');
                return;
            }

            foreach ($xml->channel->item as $item) {
                if (version_compare(CHYRP_VERSION, $item->version, "<")) {
                    #if (Config::current()->auto_update and class_exists("ZipArchive"))
                    #    self::install($item->downloadurl);

                    Flash::message(_f("Chyrp Lite v%s is available.", $item->version).
                                   ' <a href="'.$item->updateurl.'" target="_blank">'.__("Go to GitHub &rarr;").'</a>');
                    break;
                }
            }
        }

        /**
         * Function: install
         * Download and install Chyrp Lite updates from the web.
         */
        private static function install($url) {
            if (DEBUG)
                error_log("INSTALLING UPDATE: ".$url); 

            $filename = upload_from_url($url);
            $filepath = MAIN_DIR.Config::current()->uploads_path.$filename;

            $zip = new ZipArchive;
            $err = $zip->open($filepath);

            if ($err === true) {
                $zip->extractTo(MAIN_DIR);
                $zip->close();
                unlink($filepath);
                redirect("/upgrade.php?upgrade=yes", true);
                exit;
            } else
                error(__("Error"), _f("Failed to install Chyrp Lite update because of ZipArchive error: <code>%s</code>", zip_errors($err)));
        }
    }
