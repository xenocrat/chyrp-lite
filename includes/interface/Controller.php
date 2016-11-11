<?php
    /**
     * Interface: Controller
     * Describes the functions required by Controller implementations.
     */
    interface Controller {
        /**
         * Function: parse
         * Route constructor calls this to determine the action.
         */
        public function parse($route);

        /**
         * Function: display
         * Displays the page.
         */
        public function display($template);
    }
