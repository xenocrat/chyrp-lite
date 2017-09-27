<?php
    class Maptcha extends Modules implements CaptchaProvider {
        static function __install() {
            Config::current()->set("module_maptcha", array("maptcha_hashkey" => random(32)));
        }

        static function __uninstall() {
            Config::current()->remove("module_maptcha");
        }

        static function generateCaptcha() {
            $maptcha_hashkey = Config::current()->module_maptcha["maptcha_hashkey"];

            $x = rand(1,9);
            $y = rand(1,$x);
            $z = rand(1,9);

            switch ($z) {
                case 1:
                    $label = _f("How much is %d&nbsp;&#43;&nbsp;%d&nbsp;?", array($x, $y), "maptcha");
                    $value = sha1(strval($x + $y).$maptcha_hashkey);
                    break;
                case 2:
                    $label = _f("How much is %d&nbsp;&#8722;&nbsp;%d&nbsp;?", array($x, $y), "maptcha");
                    $value = sha1(strval($x - $y).$maptcha_hashkey);
                    break;
                case 3:
                    $label = _f("How much is %d&nbsp;&#215;&nbsp;%d&nbsp;?", array($x, $y), "maptcha");
                    $value = sha1(strval($x * $y).$maptcha_hashkey);
                    break;
                case 4:
                    $label = _f("How much is %d&nbsp;&plus;&nbsp;%d&nbsp;?", array($x, $y), "maptcha");
                    $value = sha1(strval($x + $y).$maptcha_hashkey);
                    break;
                case 5:
                    $label = _f("How much is %d&nbsp;&minus;&nbsp;%d&nbsp;?", array($x, $y), "maptcha");
                    $value = sha1(strval($x - $y).$maptcha_hashkey);
                    break;
                case 6:
                    $label = _f("How much is %d&nbsp;&times;&nbsp;%d&nbsp;?", array($x, $y), "maptcha");
                    $value = sha1(strval($x * $y).$maptcha_hashkey);
                    break;
                case 7:
                    $label = _f("How much is %d&nbsp;&#x0002B;&nbsp;%d&nbsp;?", array($x, $y), "maptcha");
                    $value = sha1(strval($x + $y).$maptcha_hashkey);
                    break;
                case 8:
                    $label = _f("How much is %d&nbsp;&#x02212;&nbsp;%d&nbsp;?", array($x, $y), "maptcha");
                    $value = sha1(strval($x - $y).$maptcha_hashkey);
                    break;
                case 9:
                    $label = _f("How much is %d&nbsp;&#x000D7;&nbsp;%d&nbsp;?", array($x, $y), "maptcha");
                    $value = sha1(strval($x * $y).$maptcha_hashkey);
                    break;
            }

            return '<label for="maptcha_response">'.$label.'</label>'."\n".
                   '<input type="number" name="maptcha_response" value="" placeholder="'.
                   __("Yay mathemetics!", "maptcha").'">'."\n".
                   '<input type="hidden" name="maptcha_requested" value="'.time().'">'."\n".
                   '<input type="hidden" name="maptcha_challenge" value="'.$value.'">'."\n";
        }

        static function checkCaptcha() {
            # Constant: MAPTCHA_MIN_ELAPSED
            # Minimum elapsed timed in seconds allowed between challenge and response.
            if (!defined('MAPTCHA_MIN_ELAPSED'))
                define('MAPTCHA_MIN_ELAPSED', 10);

            if (!isset($_POST['maptcha_response']) or !isset($_POST['maptcha_challenge']))
                return false;

            if (empty($_POST['maptcha_requested']) or !is_numeric($_POST['maptcha_requested']))
                return false;

            if ((time() - (int) $_POST['maptcha_requested']) < MAPTCHA_MIN_ELAPSED)
                return false;

            $maptcha_hashkey = Config::current()->module_maptcha["maptcha_hashkey"];

            $maptcha_response = preg_replace("/[^0-9]/", "", $_POST['maptcha_response']);
            $maptcha_response = sha1($maptcha_response.$maptcha_hashkey);

            return ($maptcha_response == $_POST['maptcha_challenge']);
        }
    }
