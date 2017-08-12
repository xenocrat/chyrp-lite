<?php
    require_once "lib".DIR."recaptchalib.php";

    class Recaptcha extends Modules {
        static function __install() {
            Config::current()->set("module_recaptcha",
                                   array("public_key" => null,
                                         "private_key" => null));
        }

        static function __uninstall() {
            Config::current()->remove("module_recaptcha");
        }

        public function admin_recaptcha_settings($admin) {
            if (!Visitor::current()->group->can("change_settings"))
                show_403(__("Access Denied"), __("You do not have sufficient privileges to change settings."));
    
            if (empty($_POST))
                return $admin->display("pages".DIR."recaptcha_settings");
    
            if (!isset($_POST['hash']) or $_POST['hash'] != token($_SERVER['REMOTE_ADDR']))
                show_403(__("Access Denied"), __("Invalid authentication token."));

            fallback($_POST['public_key'], "");
            fallback($_POST['private_key'], "");

            Config::current()->set("module_recaptcha",
                                   array("public_key" => $_POST['public_key'],
                                         "private_key" => $_POST['private_key']));

            Flash::notice(__("Settings updated."), "recaptcha_settings");
        }

        public function settings_nav($navs) {
            if (Visitor::current()->group->can("change_settings"))
                $navs["recaptcha_settings"] = array("title" => __("ReCAPTCHA", "recaptcha"));

            return $navs;
        }

    }

    class RecaptchaCaptcha implements Captcha {
        static function getCaptcha() {
            $public_key = Config::current()->module_recaptcha["public_key"];

            if (!empty($public_key))
                return recaptcha_get_html($public_key);
        }

        static function verifyCaptcha() {
            $private_key = Config::current()->module_recaptcha["private_key"];

            if (empty($private_key))
                return false;

            if (!isset($_POST['recaptcha_challenge_field']) or !isset($_POST['recaptcha_response_field']))
                return false;

            $check = recaptcha_check_answer($private_key,
                                            $_SERVER['REMOTE_ADDR'],
                                            $_POST['recaptcha_challenge_field'],
                                            $_POST['recaptcha_response_field']);

            return (!$check->is_valid) ? false : true ;
        }
    }
