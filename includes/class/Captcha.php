<?php

    /*
     * Chyrp -- CAPTCHA interface
     *
     * This class was created to seperate out the CAPTCHA handling code to allow for more complex systems.
     * reCAPTCHA is still offered (as a plugin).
     */

    define("PUBLIC_KEY", "6Lf6RsoSAAAAAEqUPsm4icJTg7Ph3mY561zCQ3l3");
    define("PRIVATE_KEY", "6Lf6RsoSAAAAAKn-wPxc1kE-DE0M73i206w56HEN");

    interface Captcha {
       public static function getCaptcha();
       public static function verifyCaptcha();
    }
