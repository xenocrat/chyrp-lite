<?php
    require_once INCLUDES_DIR."/class/Captcha.php";

    class Maptcha extends Modules {
        public function __init() {
            global $captchaHooks;
            $captchaHooks[] = "MaptchaCaptcha";
        }

        static function __install() {
            Config::current()->set("maptcha_hashkey", md5(random(32, true)));;
        }

        static function __uninstall() {
            Config::current()->remove("maptcha_hashkey");
        }
    }

    class MaptchaCaptcha implements Captcha {
        static function getCaptcha() {
            $config = Config::current();

            $x = rand(1,9);
            $y = rand(1,9);

            $html = "\n";
            $html.= '<label for="maptcha">'._f("How much is %d + %d ?", array($x, $y), "maptcha").'</label>'."\n";
            $html.= '<input type="text" name="maptcha_response" value="" placeholder="'.__("Yay mathemetics!", "maptcha").'">'."\n";
            $html.= '<input type="hidden" name="maptcha_challenge" value="'.md5(($x + $y).$config->maptcha_hashkey).'">'."\n";
            return $html;
        }

        static function verifyCaptcha() {
            $config = Config::current();

            if (!isset($_POST['maptcha_response']) or !isset($_POST['maptcha_challenge']))
                return false;

            if (md5(preg_replace("/[^0-9]/", "", $_POST['maptcha_response']).$config->maptcha_hashkey) != $_POST['maptcha_challenge'])
                return false;
            else
                return true;
        }
    }
