<?php
    class Maptcha extends Modules {
        static function __install() {
            Config::current()->set("module_maptcha", array("maptcha_hashkey" => random(32)));
        }

        static function __uninstall() {
            Config::current()->remove("module_maptcha");
        }
    }

    class MaptchaCaptcha implements Captcha {
        static function getCaptcha() {
            $maptcha_hashkey = Config::current()->module_maptcha["maptcha_hashkey"];

            $x = rand(1,9);
            $y = rand(1,9);

            return "\n".
                   '<label for="maptcha">'._f("How much is %d + %d ?", array($x, $y), "maptcha").'</label>'."\n".
                   '<input type="number" name="maptcha_response" value="" placeholder="'.__("Yay mathemetics!", "maptcha").'">'."\n".
                   '<input type="hidden" name="maptcha_challenge" value="'.sha1(strval($x + $y).$maptcha_hashkey).'">'."\n";
        }

        static function verifyCaptcha() {
            $maptcha_hashkey = Config::current()->module_maptcha["maptcha_hashkey"];

            if (!isset($_POST['maptcha_response']) or !isset($_POST['maptcha_challenge']))
                return false;

            if (sha1(preg_replace("/[^0-9]/", "", $_POST['maptcha_response']).$maptcha_hashkey) != $_POST['maptcha_challenge'])
                return false;
            else
                return true;
        }
    }
