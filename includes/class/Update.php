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
            $config = Config::current();

            $xml = simplexml_load_string(get_remote(UPDATE_XML, 3));
            $config->set("check_updates_last", time());

            if ($xml == false) {
                Flash::warning(__("Unable to check for updates.").
                                  ' <a href="'.UPDATE_PAGE.'" target="_blank">'.__("Go to GitHub &rarr;").'</a>');
                return;
            }

            foreach ($xml->channel->item as $item) {
                if (version_compare(CHYRP_VERSION, $item->version, "<")) {
                    #if ($config->install_updates and !Flash::exists())
                    #    self::install($item->downloadurl);
                    #else
                        Flash::message(_f("Chyrp Lite v%s is available.", $item->version).
                                          ' <a href="'.$item->updateurl.'" target="_blank">'.__("Go to GitHub &rarr;").'</a>');

                    break;
                }
            }
        }

        /**
         * Function: install
         * Download and install Chyrp Lite updates from GitHub.
         */
        private static function install($url) {
            if (!class_exists("ZipArchive"))
                return;

            if (DEBUG)
                error_log("INSTALLING UPDATE: ".$url); 

            $filename = upload_from_url($url, 3, 30);
            $filepath = uploaded($filename, false);

            $zip = new ZipArchive;
            $err = $zip->open($filepath);

            if ($err === true) {
                for ($i=0; $i < $zip->numFiles; $i++) { 
                    $name = $zip->getNameIndex($i);

                    if ($name != rtrim($name, "/"))
                        continue; # Skip this item because it is a folder.

                    $folders = explode("/", $name);
                    array_shift($folders); # Disregard the base directory.

                    $itemname = array_pop($folders);
                    $itempath = MAIN_DIR;

                    foreach ($folders as $folder) {
                        $itempath.= DIR.$folder;

                        if (!file_exists($itempath))
                            mkdir($itempath, 0755);
                    }

                    copy("zip://".$filepath."#".$name, $itempath.DIR.$itemname);
                }

                $zip->close();
                unlink($filepath);
                redirect("/upgrade.php?upgrade=yes", true);
                exit;
            } else
                error(__("Error"), _f("Failed to install Chyrp Lite update because of ZipArchive error: <code>%s</code>", zip_errors($err)));
        }
    }
