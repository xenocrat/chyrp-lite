<?php
    /**
     * Interface: Captcha
     * Defines the interface to be implemented by Captcha providers.
     * Providers register by adding their class name to @global $captchaHooks[]@.
     */
    interface Captcha {
       public static function getCaptcha();
       public static function verifyCaptcha();
    }
