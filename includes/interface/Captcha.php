<?php
    /**
     * Interface: Captcha
     * Describes the functions required by Captcha implementations.
     */
    interface Captcha {
        /**
         * Function: getCaptcha
         * Returns the HTML form elements for the captcha challenge.
         */
        public static function getCaptcha();

        /**
         * Function: verifyCaptcha
         * Verifies the response and returns true (success) or false (failure).
         */
        public static function verifyCaptcha();
    }
