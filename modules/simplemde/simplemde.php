<?php
    class Simplemde extends Modules {
        public function admin_head() {
            $config = Config::current();

            echo '<!-- SimpleMDE -->'."\n".
                 '<link rel="stylesheet" href="'.$config->chyrp_url.
                 '/modules/simplemde/simplemde.min.css" type="text/css" media="all">'."\n".
                 '<script src="'.$config->chyrp_url.
                 '/modules/simplemde/simplemde.min.js" type="text/javascript" charset="UTF-8"></script>'."\n";
        }

        public function admin_javascript() {
            include MODULES_DIR.DIR."simplemde".DIR."javascript.php";
        }
    }
