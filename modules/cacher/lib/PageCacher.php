<?php
    class PageCacher {
        public function __construct($url) {
            $this->base = CACHES_DIR.DIR."pages";
            $this->life = Config::current()->module_cacher["cache_expire"];
            $this->url  = $url;
            $this->user = Visitor::current()->id;
            $this->path = $this->base.DIR.$this->user;
            $this->file = $this->path.DIR.token($this->url).".html";

            # If the directories do not exist and cannot be created, or are not writable, cancel execution.
            if (((!is_dir($this->base) and !@mkdir($this->base)) or !is_writable($this->base)) or
                ((!is_dir($this->path) and !@mkdir($this->path)) or !is_writable($this->path)))
                cancel_module("cacher", __("Cacher module cannot write caches to disk.", "cacher"));
        }

        private function available($route) {
            return (MAIN and
                ($route->controller instanceof MainController) and
                empty($_POST) and
                !$route->controller->feed and
                !Flash::exists() and
                (file_exists($this->file) and filemtime($this->file) + $this->life >= time()));
        }

        private function cacheable($route) {
            return (MAIN and
                ($route->controller instanceof MainController) and
                empty($_POST) and
                !$route->controller->feed and
                !Flash::exists() and
                (!file_exists($this->file) or filemtime($this->file) + $this->life < time()));
        }

        public function get($route) {
            if (self::available($route)) {
                if (DEBUG)
                    error_log("SERVING page cache for ".$this->url);

                $contents = @file_get_contents($this->file);

                if ($contents !== false and $contents !== "") {
                    header("Content-Type: text/html; charset=UTF-8");
                    header("Last-Modified: ".date("r", filemtime($this->file)));
                    exit($contents);
                }
            }
        }

        public function set($route) {
            if (self::cacheable($route)) {
                if (DEBUG)
                    error_log("GENERATING page cache for ".$this->url);

                $contents = ob_get_contents();

                if ($contents !== false and $contents !== "")
                    @file_put_contents($this->file, $contents);
            }
        }

        public function regenerate() {
            if (DEBUG)
                error_log("REGENERATING page caches");

            foreach ((array) glob($this->base.DIR."*".DIR."*.html") as $file)
                @unlink($file);
        }

        public function regenerate_user($user = null) {
            fallback($user, $this->user);

            if (DEBUG)
                error_log("REGENERATING page caches for user ID ".$user);

            foreach ((array) glob($this->base.DIR.$user.DIR."*.html") as $file)
                @unlink($file);
        }

        public function regenerate_url($url = null) {
            fallback($url, $this->url);

            if (DEBUG)
                error_log("REGENERATING page caches for URL ".$url);

            foreach ((array) glob($this->base.DIR."*".DIR.token($url).".html") as $file)
                @unlink($file);
        }
    }
