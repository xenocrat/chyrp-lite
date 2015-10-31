<?php
    class MailToFile extends Modules {
        static function __install() {
            $config = Config::current();

            $output = "<?php header(\"Status: 403\"); exit(\"Access denied.\"); ?>\n";
            $output.= "MIME-Version: 1.0\r\n";
            $output.= "Content-Type: multipart/digest; boundary=\"---correspondence---\"\r\n";
            $output.= "\r\n---correspondence---\r\n";

            if (!file_exists(MAIN_DIR.DIR."digest.txt.php"))
                if (!@file_put_contents(MAIN_DIR."/digest.txt.php", $output))
                    error(__("Error"), _f("Cannot write digest file <code>%s</code>", MAIN_DIR.DIR."digest.txt.php", "mail_to_file"));
        }

        static function __uninstall($confirm) {
            if ($confirm)
                @unlink(MAIN_DIR."/digest.txt.php");
        }

        public function send_mail($function) {
            return array('MailToFile', 'mail_digest');
        }

        static function mail_digest($to, $subject, $message, $headers) {
            $output = "\r\n".$headers."\r\n";
            $output.= "To: ".$to."\r\n";
            $output.= "Date: ".datetime()."\r\n";
            $output.= "Subject: ".$subject."\r\n\r\n";
            $output.= $message."\r\n\r\n";
            $output.= "---correspondence---\r\n";

            if (@file_put_contents(MAIN_DIR.DIR."digest.txt.php", $output, FILE_APPEND))
                return true;
            else
                return false;
        }
    }
