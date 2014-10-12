<?php

    /*
     * Chyrp -- CAPTCHA interface
     *
     * This class was created to seperate out the CAPTCHA handling code to allow for more complex systems.
     * reCAPTCHA is still offered (as a plugin).
     */

    interface Captcha {
       public static function getCaptcha();
       public static function verifyCaptcha();
    }
