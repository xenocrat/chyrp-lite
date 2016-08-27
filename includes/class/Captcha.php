<?php
    /**
     * Interface: Captcha
     * Defines the Captcha interface.
     */
    interface Captcha {
    	# Returns the form elements for the captcha challenge.
		public static function getCaptcha();

		# Verifies the response and returns true (success) or false (failure).
		public static function verifyCaptcha();
    }
