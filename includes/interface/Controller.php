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
         * Function: exempt
         * Route constructor calls this to determine "view_site" exemptions.
         */
        public function exempt($action);
    }
