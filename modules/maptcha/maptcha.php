<?php
    require_once INCLUDES_DIR."/class/Captcha.php";

    class Maptcha extends Modules {
        public function __init() {
            global $captchaHooks;
            $captchaHooks[] = "MaptchaCaptcha";
        }
    }

    class MaptchaCaptcha implements Captcha {
        static function getCaptcha() {
            $x = rand(1,9);
            $y = rand(1,9);

            $html = "\n";
            $html.= '<label for="maptcha">'._f("How much is %d + %d ?", array($x, $y), "maptcha").'</label>'."\n";
            $html.= '<input type="text" name="maptcha_response" value="" placeholder="'.__("Yay mathemetics!", "maptcha").'">'."\n";
            $html.= '<input type="hidden" name="maptcha_challenge" value="'.md5(($x + $y).self::secret()).'">'."\n";
            return $html;
        }

        static function verifyCaptcha() {
            if (!isset($_POST['maptcha_response']) or !isset($_POST['maptcha_challenge']))
                return false;

            $response = sanitize($_POST['maptcha_response'], true, true, 0);

            if (md5($response.self::secret()) != $_POST['maptcha_challenge'])
                return false;
            else
                return true;
        }

        private function secret() {
            return md5(Config::current()->secure_hashkey);
        }
    }
