<?php
    class Maptcha extends Modules implements CaptchaProvider {
        static function __install() {
            Config::current()->set("module_maptcha", array("maptcha_hashkey" => random(32)));
        }

        static function __uninstall() {
            Config::current()->remove("module_maptcha");
        }

        static function getCaptcha() {
            $maptcha_hashkey = Config::current()->module_maptcha["maptcha_hashkey"];

            $x = rand(1,9);
            $y = rand(1,9);

            return '<label for="maptcha_response">'.
                   _f("How much is %d + %d ?", array($x, $y), "maptcha").'</label>'."\n".
                   '<input type="number" name="maptcha_response" value="" placeholder="'.
                   __("Yay mathemetics!", "maptcha").'">'."\n".
                   '<input type="hidden" name="maptcha_challenge" value="'.
                   sha1(strval($x + $y).$maptcha_hashkey).'">'."\n";
        }

        static function verifyCaptcha() {
            $maptcha_hashkey = Config::current()->module_maptcha["maptcha_hashkey"];

            if (!isset($_POST['maptcha_response']) or !isset($_POST['maptcha_challenge']))
                return false;

            $maptcha_response = preg_replace("/[^0-9]/", "", $_POST['maptcha_response']);
            $maptcha_response = sha1($maptcha_response.$maptcha_hashkey);

            return ($maptcha_response == $_POST['maptcha_challenge']);
        }
    }
