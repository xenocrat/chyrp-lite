<?php
    class ContentSecurityPolicy extends Modules {
        public function runtime(
        ): void {
            $error_style_hash = base64_encode(
                hash("sha256", error_style(), true)
            );

            header(
                "Content-Security-Policy:".
                " default-src 'self';".
                " style-src 'self' 'sha256-".$error_style_hash."';".
                " script-src 'self';".
                " frame-ancestors 'self';".
                " form-action 'self';"
            );
        }
    }
