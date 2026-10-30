<?php
    class Maptcha extends Modules implements CaptchaProvider {
        const MAPTCHA_MIN_ELAPSED = 5;
        const MAPTCHA_MAX_ELAPSED = 300;

        private static $hashkey = "";

        static function __install(
        ): void {
            Config::current()->set(
                "module_maptcha",
                array("maptcha_hashkey" => random(32))
            );
        }

        static function __uninstall(
        ): void {
            Config::current()->remove("module_maptcha");
        }

        public function __init(
        ): void {
            self::$hashkey = Config::current()->module_maptcha["maptcha_hashkey"];
        }

        static function generateCaptcha(
        ): string {
            $t = time();
            $x = rand(1,9);
            $y = rand(1,$x);
            $z = rand(1,9);
            $n = array($x, $y);

            switch ($z) {
                case 1:
                    $label = _f("How much is %d&nbsp;&#43;&nbsp;%d&nbsp;?", $n, "maptcha");
                    $value = token(
                        array(
                            strval($x + $y),
                            $t,
                            self::$hashkey
                        )
                    );
                    break;
                case 2:
                    $label = _f("How much is %d&nbsp;&#8722;&nbsp;%d&nbsp;?", $n, "maptcha");
                    $value = token(
                        array(
                            strval($x - $y),
                            $t,
                            self::$hashkey
                        )
                    );
                    break;
                case 3:
                    $label = _f("How much is %d&nbsp;&#215;&nbsp;%d&nbsp;?", $n, "maptcha");
                    $value = token(
                        array(
                            strval($x * $y),
                            $t,
                            self::$hashkey
                        )
                    );
                    break;
                case 4:
                    $label = _f("How much is %d&nbsp;&plus;&nbsp;%d&nbsp;?", $n, "maptcha");
                    $value = token(
                        array(
                            strval($x + $y),
                            $t,
                            self::$hashkey
                        )
                    );
                    break;
                case 5:
                    $label = _f("How much is %d&nbsp;&minus;&nbsp;%d&nbsp;?", $n, "maptcha");
                    $value = token(
                        array(
                            strval($x - $y),
                            $t,
                            self::$hashkey
                        )
                    );
                    break;
                case 6:
                    $label = _f("How much is %d&nbsp;&times;&nbsp;%d&nbsp;?", $n, "maptcha");
                    $value = token(
                        array(
                            strval($x * $y),
                            $t,
                            self::$hashkey
                        )
                    );
                    break;
                case 7:
                    $label = _f("How much is %d&nbsp;&#x0002B;&nbsp;%d&nbsp;?", $n, "maptcha");
                    $value = token(
                        array(
                            strval($x + $y),
                            $t,
                            self::$hashkey
                        )
                    );
                    break;
                case 8:
                    $label = _f("How much is %d&nbsp;&#x02212;&nbsp;%d&nbsp;?", $n, "maptcha");
                    $value = token(
                        array(
                            strval($x - $y),
                            $t,
                            self::$hashkey
                        )
                    );
                    break;
                case 9:
                    $label = _f("How much is %d&nbsp;&#x000D7;&nbsp;%d&nbsp;?", $n, "maptcha");
                    $value = token(
                        array(
                            strval($x * $y),
                            $t,
                            self::$hashkey
                        )
                    );
                    break;
            }

            return '<label for="maptcha_response">'.$label.'</label>'."\n".
                   '<input type="number" name="maptcha_response"'.
                   ' id="maptcha_response" value="" placeholder="'.
                   __("Yay mathemetics!", "maptcha").'">'."\n".
                   '<input type="hidden" name="maptcha_created_at" value="'.
                   $t.'">'."\n".
                   '<input type="hidden" name="maptcha_challenge" value="'.
                   $value.'">'."\n";
        }

        static function checkCaptcha(
        ): bool {
            $config = Config::current();

            if (!isset($_POST['maptcha_challenge']))
                return false;

            if (!isset($_POST['maptcha_response']))
                return false;

            if (empty($_POST['maptcha_created_at']))
                return false;

            if (!is_numeric($_POST['maptcha_created_at']))
                return false;

            $challenge = $_POST['maptcha_challenge'];
            $time = time();
            $created_at = (int) $_POST['maptcha_created_at'];

            if ($time - $created_at < self::MAPTCHA_MIN_ELAPSED)
                return false;

            if ($time - $created_at > self::MAPTCHA_MAX_ELAPSED)
                return false;

            $response = token(
                array(
                    preg_replace(
                        "/[^0-9]/",
                        "",
                        $_POST['maptcha_response']
                    ),
                    $created_at,
                    self::$hashkey
                )
            );

            return hash_equals($challenge, $response);
        }
    }
