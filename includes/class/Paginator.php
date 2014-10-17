<?php
    /**
     * Class: Paginator
     * Paginates over an array.
     */
    class Paginator {
        # Array: $array
        # The original, unmodified data.
        public $array;

        # Integer: $per_page
        # Number of items per page.
        public $per_page;

        # String: $name
        # Name of the $_GET value for the current page.
        public $name;

        # Boolean: $model
        # Should the <$array> items be treated as Models?
        # In this case, <$array> should be in the form of array(<ids>, "ModelName")
        public $model;

        # Integer: $total
        # Total number of items to paginate.
        public $total;

        # Integer: $page
        # The current page.
        public $page;

        # Integer: $pages
        # Total number of pages.
        public $pages;

        # Array: $result
        # The result of the pagination.
        # @paginated@, @paginate@, and @list@ are references to this.
        public $result = array();

        # Array: $names
        # An array of the currently-used pagination URL parameters.
        static $names = array();

        /**
         * Function: __construct
         * Prepares an array for pagination.
         *
         * Parameters:
         *     $array - The array to paginate.
         *     $per_page - Number of items per page.
         *     $name - The name of the $_GET parameter to use for determining the current page.
         *     $model - If this is true, each item in $array that gets shown on the page will be
         *              initialized as a model of whatever is passed as the second argument to $array.
         *              The first argument of $array is expected to be an array of IDs.
         *     $page - Page number to start at.
         *
         * Returns:
         *     A paginated array of length $per_page or smaller.
         */
        public function __construct($array, $per_page = 5, $name = "page", $model = null, $page = null) {
            self::$names[] = $name;

            $this->array = (array) $array;

            $this->per_page = $per_page;
            $this->name = $name;
            $this->model = fallback($model, (count($this->array) == 2 and is_array($this->array[0]) and is_string($this->array[1]) and class_exists($this->array[1])));

            if ($model)
                list($this->array, $model_name) = $this->array;

            $this->total = count($this->array);
            $this->page = oneof($page, @$_GET[$name], 1);
            $this->pages = ceil($this->total / $this->per_page);

            $offset = ($this->page - 1) * $this->per_page;

            $this->result = array();

            if ($model) {
                for ($i = $offset; $i < ($offset + $this->per_page); $i++)
                    if (isset($this->array[$i]))
                        $this->result[] = new $model_name(null, array("read_from" => $this->array[$i]));
            } else
                $this->result = array_slice($this->array, $offset, $this->per_page);

            $shown_dates = array();
            if ($model)
                foreach ($this->result as &$result)
                    if (isset($result->created_at)) {
                        $pinned = (isset($result->pinned) and $result->pinned);
                        $shown = in_array(when("m-d-Y", $result->created_at), $shown_dates);

                        $result->first_of_day = (!$pinned and !$shown and !AJAX);

                        if (!$pinned and !$shown)
                            $shown_dates[] = when("m-d-Y", $result->created_at);
                    }

            $this->paginated = $this->paginate = $this->list =& $this->result;
        }

        /**
         * Function: next
         * Returns the next pagination sequence.
         */
        public function next() {
            return new self($this->array, $this->per_page, $this->name, $this->model, $this->page + 1);
        }

        /**
         * Function: prev
         * Returns the next pagination sequence.
         */
        public function prev() {
            return new self($this->array, $this->per_page, $this->name, $this->model, $this->page - 1);
        }

        /**
         * Function: next_page
         * Checks whether or not it makes sense to show the Next Page link.
         */
        public function next_page() {
            return ($this->page < $this->pages and $this->pages != 1 and $this->pages != 0);
        }

        /**
         * Function: prev_page
         * Checks whether or not it makes sense to show the Previous Page link.
         */
        public function prev_page() {
            return ($this->page != 1 and $this->page <= $this->pages);
        }

        /**
         * Function: next_link
         * Outputs a link to the next page.
         *
         * Parameters:
         *     $text - The text for the link.
         *     $class - The CSS class for the link.
         *     $page - Page number to link to.
         *     $anchor - An anchor target.
         */
        public function next_link($text = null, $class = "next_page", $page = null, $anchor = null) {
            if (!$this->next_page())
                return;

            if (isset($anchor))
                $anchor = '#'.$anchor;

            fallback($text, __("Next &rarr;"));
            echo '<a  rel="next" class="'.$class.'" id="next_page_'.$this->name.'" href="'.$this->next_page_url($page).$anchor.'">'.
                     $text.
                 '</a>';
        }

        /**
         * Function: prev_link
         * Outputs a link to the previous page.
         *
         * Parameters:
         *     $text - The text for the link.
         *     $class - The CSS class for the link.
         *     $page - Page number to link to.
         *     $anchor - An anchor target.
         */
        public function prev_link($text = null, $class = "prev_page", $page = null, $anchor = null) {
            if (!$this->prev_page())
                return;

            if (isset($anchor))
                $anchor = '#'.$anchor;

            fallback($text, __("&larr; Previous"));
            echo '<a rel="prev" class="'.$class.'" id="prev_page_'.$this->name.'" href="'.$this->prev_page_url($page).$anchor.'">'.
                     $text.
                 '</a>';
        }

        /**
         * Function: next_page_url
         * Returns the URL to the next page.
         *
         * Parameters:
         *     $page - Page number to link to.
         */
        public function next_page_url($page = null) {
            $config = Config::current();

            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
            $request = $protocol.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];

            # Only used for adding to the end of the URL and clean URLs is off.
            $mark = (substr_count($request, "?")) ? "&amp;" : "?" ;

            fallback($page, $this->page + 1);

            # No page is set, add it to the end.
            if (!isset($_GET[$this->name]))
                return ($config->clean_urls and !ADMIN) ?
                       rtrim($request, "/")."/".$this->name."/".$page :
                       $request.$mark.$this->name."=".$page ;

            return ($config->clean_urls and !ADMIN) ?
                   preg_replace("/(\/{$this->name}\/([0-9]+)|$)/", "/".$this->name."/".$page, $request, 1) :
                   preg_replace("/((\?|&){$this->name}=([0-9]+)|$)/", "\\2".$this->name."=".$page, $request, 1) ;
        }

        /**
         * Function: prev_page_url
         * Returns the URL to the previous page.
         *
         * Parameters:
         *     $page - Page number to link to.
         */
        public function prev_page_url($page = null) {
            $config = Config::current();

            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
            $request = $protocol.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];

            # Only used for adding to the end of the URL and clean URLs is off.
            $mark = (substr_count($request, "?")) ? "&amp;" : "?" ;

            fallback($page, $this->page - 1);

            # No page is set, add it to the end.
            if (!isset($_GET[$this->name]))
                return ($config->clean_urls and !ADMIN) ?
                       rtrim($request, "/")."/".$this->name."/".$page :
                       $request.$mark.$this->name."=".$page ;

            return ($config->clean_urls and !ADMIN) ?
                   preg_replace("/(\/{$this->name}\/([0-9]+)|$)/", "/".$this->name."/".$page, $request, 1) :
                   preg_replace("/((\?|&){$this->name}=([0-9]+)|$)/", "\\2".$this->name."=".$page, $request, 1) ;
        }
    }
