<?php
    class FileCacher{
        public function __construct($url, $config) {
            $this->user = (logged_in()) ? Visitor::current()->login : "guest" ;
            $this->path = INCLUDES_DIR."/caches/".sanitize($this->user);

            $this->caches = INCLUDES_DIR."/caches";
            $this->url = $url;
            $this->file = $this->path."/".md5($this->url).".html";

            # If the cache directory is not writable, disable this module and cancel execution.
            if (!is_writable($this->caches))
                cancel_module("cacher");

            # Remove all expired files.
            $this->remove_expired();
        }

        public function url_available(){
            return file_exists($this->file);
        }

        public function get(){
            if (DEBUG)
                error_log("SERVING cache file for ".$this->url."...");

            $cache = array('contents' => file_get_contents($this->file), 'headers' => array());

            if (substr_count($cache['contents'], "<feed"))
                $cache["headers"][] = "Content-Type: application/atom+xml; charset=UTF-8";

            return $cache;
        }

        public function set($value){
            if (DEBUG)
                error_log("GENERATING cache file for ".$this->url."...");

            # Generate the user's directory.
            if (!file_exists($this->path))
                mkdir($this->path);

            file_put_contents($this->file, $value);
        }

        public function remove_expired(){
            foreach ((array) glob($this->caches."/*/*.html") as $file) {
                if (time() - filemtime($file) > Config::current()->cache_expire)
                    @unlink($file);

                $dir = dirname($file);
                if (!count((array) glob($dir."/*")))
                    @rmdir($dir);
            }
        }

        public function regenerate() {
            if (DEBUG)
                error_log("REGENERATING");

            foreach ((array) glob($this->caches."/*/*.html") as $file)
                @unlink($file);
        }

        public function regenerate_local($user = null) {
            if (DEBUG)
                error_log("REGENERATING local user ".$this->user."...");

            $directory = (isset($user)) ? $this->caches."/".$user : $this->path ;
            foreach ((array) glob($directory."/*.html") as $file)
                @unlink($file);
        }

        public function remove_caches_for($url) {
            if (DEBUG)
                error_log("REMOVING caches for ".$url."...");

            foreach ((array) glob($this->caches."/*/".md5($url).".html") as $file)
                @unlink($file);
        }
    }

