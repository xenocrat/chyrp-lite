<?php
    class MemCacher{
        public function __construct($url, $config){
            $raw_hosts = (array)$config->cache_memcached_hosts;
            $this->user = (logged_in()) ? Visitor::current()->login : "guest" ;
            $this->memcache = new Memcache();
            $this->url = $url;
            $this->config = $config;

            $disable_module = true;

            foreach($raw_hosts as $raw){
                $raw = trim($raw);
                if($raw == '') continue;
                $stack = explode(':', $raw);
                $host = false;
                $port = 11211;

                if(count($stack) == 9 or count($stack) == 2){ # ipv6 with port is 9, ipv4 with port is 2
                    $port = array_pop($stack);
                }
                if(count($stack) == 1){
                    $host = $stack[0];
                }
                if(count($stack) == 8){ # ipv6 is 8 entries
                    $host = implode(':', $stack); 
                }
                if($host === false and count($stack) > 0){ # probably a uri for other transit
                    $host = implode(':', $stack);
                    $port = 0; # other transit requires a port of 0
                }

                if($host === false){
                    error_log("Memcached error: $raw is an invalid host address");
                }else{
                    $this->memcache->addServer($host, $port);
                    $disable_module = false;
                }
            }

            //$disable_module = true;
            if ($disable_module)
                cancel_module("cacher");
        }

        function build_key($value){
            $prefix = '';
            if($prefix and $prefix != ''){
                $prefix = "chyrp_$prefix_";
            }else{
                $prefix = "chyrp_";
            }
            return $prefix . $value;
        }

        public function url_available(){
            $this->keys = array();
            $this->keys[] = $this->build_key("global");
            $this->keys[] = $this->build_key("url=" . $this->url);
            $this->keys[] = $this->build_key("user=" . $this->user);
            $this->keys[] = $this->user_url_key = $this->build_key("user_url=/" . $this->user . '/' . $this->url);

            $this->cache_result = $this->memcache->get($this->keys);

            return $this->cached_copy_valid();
        }

        function cached_copy_valid(){
            if(!array_key_exists($this->user_url_key, $this->cache_result))
                return false;

            $copy = $this->cache_result[$this->user_url_key];
            $time = $copy['timestamp'];
            foreach(array_keys($this->cache_result) as $k){

              if($k != $this->user_url_key && $this->cache_result[$k] > $time)
                return false;

            }

            return true;
        }

        public function get(){
            $hash = $this->cache_result[$this->user_url_key];

            if(DEBUG)
                error_log("Memcache: From cache, modified at " . $hash['timestamp'] . " - " . $this->user_url_key);

            $cache = array('contents' => $hash['value'], 'headers' => array());
            return $cache;
        }
  
        public function set($value){
            $hash = array( 'timestamp' => time(), 'value' => $value );
            $result = $this->memcache->set($this->user_url_key, $hash, false, Config::current()->cache_expire);
            if(!$result){
              exit("Memcache: set failed for " . $this->user_url_key);
            }
        }
  
        public function regenerate() {
            $this->memcache->set($this->build_key('global'), time());
        }

        public function regenerate_local($user = null) {
            if($user == null) $user = 'guest';
            $this->memcache->set($this->build_key("user=" . $user), time());
        }

        public function remove_caches_for($url) {
            $this->memcache->set($this->build_key("url=" . $url), time());
        }
    }

