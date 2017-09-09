<?php
    /**
     * Interface: CaptchaProvider
     * Describes the functions required by CaptchaProvider implementations.
     */
    interface CaptchaProvider {
        /**
         * Function: generateCaptcha
         * Returns the HTML form elements for the captcha challenge.
         */
        public static function generateCaptcha();

        /**
         * Function: checkCaptcha
         * Checks the response and returns true (success) or false (failure).
         */
        public static function checkCaptcha();
    }
