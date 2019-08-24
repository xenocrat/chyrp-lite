<?php
    class HTMLCacher {
        private $base;
        private $life;
        private $file;
        private $path;

        public function __construct($url) {
            $this->base = CACHES_DIR.DIR."html";
            $this->life = Config::current()->module_cacher["cache_expire"];
            $this->file = token(array($url, session_id())).".html";
            $this->path = $this->base.DIR.$this->file;

            # Cancel execution if cache files cannot be written.
            if ((!is_dir($this->base) and !@mkdir($this->base)) or !is_writable($this->base))
                cancel_module("cacher", __("Cacher module cannot write caches to disk.", "cacher"));
        }

        private function available($route) {
            return (MAIN and !PREVIEWING and
                ($route->controller instanceof MainController) and
                empty($_POST) and
                !$route->controller->feed and
                !Flash::exists() and
                (file_exists($this->path) and
                filemtime($this->path) + $this->life >= time()));
        }

        private function cacheable($route) {
            return (MAIN and !PREVIEWING and
                ($route->controller instanceof MainController) and
                empty($_POST) and
                !$route->controller->feed and
                !Flash::exists() and
                (!file_exists($this->path) or
                filemtime($this->path) + $this->life < time()));
        }

        public function get($route) {
            if (self::available($route)) {
                if (DEBUG)
                    error_log("SERVING HTML cache file ".$this->file);

                $contents = @file_get_contents($this->path);

                if ($contents !== false and $contents !== "") {
                    header("Content-Type: text/html; charset=UTF-8");
                    header("Last-Modified: ".date("r", filemtime($this->path)));
                    exit($contents);
                }
            }
        }

        public function set($route) {
            if (self::cacheable($route)) {
                if (DEBUG)
                    error_log("GENERATING HTML cache file ".$this->file);

                $contents = ob_get_contents();

                if ($contents !== false and $contents !== "")
                    @file_put_contents($this->path, $contents);
            }
        }

        public function regenerate() {
            if (DEBUG)
                error_log("REGENERATING HTML caches");

            foreach ((array) glob($this->base.DIR."*.html") as $file)
                @unlink($file);
        }
    }
