<?php
    require_once INCLUDES_DIR."/class/Captcha.php";
    require_once "lib/recaptchalib.php";

    class Recaptcha extends Modules {
        public function __init() {
            global $captchaHooks;
            $captchaHooks[] = "RecaptchaCaptcha";
        }

        static function __install() {
            $set = array(Config::current()->set("module_recaptcha",
                                            array("public_key" => null,
                                                  "private_key" => null)));
        }

        static function __uninstall() {
            Config::current()->remove("module_recaptcha");
        }

        static function admin_recaptcha_settings($admin) {
            if (!Visitor::current()->group->can("change_settings"))
                show_403(__("Access Denied"), __("You do not have sufficient privileges to change settings."));
    
            if (empty($_POST))
                return $admin->display("recaptcha_settings");
    
            if (!isset($_POST['hash']) or $_POST['hash'] != Config::current()->secure_hashkey)
                show_403(__("Access Denied"), __("Invalid security key."));

            $set = array(Config::current()->set("module_recaptcha",
                                            array("public_key" => $_POST['public_key'],
                                                  "private_key" => $_POST['private_key'])));

            if (!in_array(false, $set))
                Flash::notice(__("Settings updated."), "/admin/?action=recaptcha_settings");
        }

        static function settings_nav($navs) {
            if (Visitor::current()->group->can("change_settings"))
                $navs["recaptcha_settings"] = array("title" => __("ReCAPTCHA", "recaptcha"));
            return $navs;
        }

    }

    class RecaptchaCaptcha implements Captcha {
        static function getCaptcha() {
            $public_key = Config::current()->module_recaptcha["public_key"];

            if (!empty($public_key))
                return recaptcha_get_html();
        }

        static function verifyCaptcha() {
            $private_key = Config::current()->module_recaptcha["private_key"];

            if (empty($private_key))
                return false;

            $resp = recaptcha_check_answer($private_key,
                                           $_SERVER['REMOTE_ADDR'],
                                           $_POST['recaptcha_challenge_field'],
                                           $_POST['recaptcha_response_field']);
            if (!$resp->is_valid)
                return false;
            else
                return true;
        }
    }
