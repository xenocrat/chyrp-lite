<?php
    class PasswordProtection extends Modules {
        const EXPONENT_VISITOR_IP = 1.0;
        const EXPONENT_SESSION_ID = 2.0;
        const EXPONENT_USER_ID    = 3.0;
        const SURVEILLANCE_WINDOW = 3600;
        const DATABASE_LIFETIME   = 86400;

        static function __install(
        ): void {
            SQL::current()->create(
                table:"logins",
                cols:array(
                    "success BOOLEAN DEFAULT '0'",
                    "user_id INTEGER",
                    "session_id VARCHAR(40) DEFAULT ''",
                    "visitor_ip VARCHAR(45) DEFAULT ''",
                    "created_at DATETIME DEFAULT NULL"
                )
            );
        }

        static function __uninstall(
            $confirm
        ): void {
            if ($confirm)
                SQL::current()->drop("logins");
        }

        public function authenticate_user(
            $user,
            $result
        ): void {
            $sql = SQL::current();

            $sql->insert(
                table:"logins",
                data:array(
                    "success"    => $result,
                    "user_id"    => $user->id,
                    "session_id" => session_id(),
                    "visitor_ip" => $_SERVER['REMOTE_ADDR'],
                    "created_at" => datetime()
                )
            );

            if (random_int(1, 100) === 42) {
                if (DEBUG)
                    error_log("LOGIN gc");

                $max_age = datetime(
                    time() - self::DATABASE_LIFETIME
                );

                $sql->delete(
                    "logins",
                    array("created_at <" => $max_age)
                );
            }
        }

        public function user_authenticating(
        ): void {
            $sql = SQL::current();
            $trigger = Trigger::current();

            $time = time();
            $from = datetime($time - self::SURVEILLANCE_WINDOW);

            # IP address:

            $visitor_ip = $_SERVER['REMOTE_ADDR'];

            $attempts = $sql->select(
                tables:"logins",
                order:"created_at DESC",
                conds:array(
                    "success" => false,
                    "visitor_ip" => $visitor_ip,
                    "created_at >" => $from
                )
            )->fetchAll();

            $latest = strtotime($attempts[0]["created_at"] ?? 0);
            $delay = count($attempts) ** self::EXPONENT_VISITOR_IP;
            $timeout = round($latest + $delay);

            if ($time < $timeout) {
                $when = _w("c", $timeout);

                error(
                    __("Access Denied"),
                    _f("You cannot log in until %s.", $when, "password_protection"),
                    code:429
                );
            }

            # Session ID:

            $session_id = session_id();

            $attempts = $sql->select(
                tables:"logins",
                order:"created_at DESC",
                conds:array(
                    "success" => false,
                    "session_id" => $session_id,
                    "created_at >" => $from
                )
            )->fetchAll();

            $latest = strtotime($attempts[0]["created_at"] ?? 0);
            $delay = count($attempts) ** self::EXPONENT_SESSION_ID;
            $timeout = round($latest + $delay);

            if ($time < $timeout) {
                $when = _w("c", $timeout);

                error(
                    __("Access Denied"),
                    _f("You cannot log in until %s.", $when, "password_protection"),
                    code:429
                );
            }

            # User ID:

            $user = new User(
                array("login" => $_POST['login'])
            );

            if ($user->no_results)
                return;

            $attempts = $sql->select(
                tables:"logins",
                order:"created_at DESC",
                conds:array(
                    "success" => false,
                    "user_id" => $user->id,
                    "created_at >" => $from
                )
            )->fetchAll();

            $latest = strtotime($attempts[0]["created_at"] ?? 0);
            $delay = count($attempts) ** self::EXPONENT_USER_ID;
            $timeout = round($latest + $delay);

            if ($time < $timeout) {
                $when = _w("c", $timeout);

                error(
                    __("Access Denied"),
                    _f("You cannot log in until %s.", $when, "password_protection"),
                    code:429
                );
            }
        }
    }
