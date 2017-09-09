<?php
    /**
     * Interface: CaptchaProvider
     * Describes the functions required by CaptchaProvider implementations.
     */
    interface CaptchaProvider {
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
