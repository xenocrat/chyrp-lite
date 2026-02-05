<?php
    class ContentSecurityPolicy extends Modules {
        private $nonce;
        private $hash;

        public function runtime(
        ): void {
            $this->nonce = base64_encode(
                random(32)
            );

            $this->hash = base64_encode(
                hash("sha256", error_style(), true)
            );

            header(
                "Content-Security-Policy:".
                " default-src 'self';".
                " style-src 'self' 'nonce-".$this->nonce."' 'sha256-".$this->hash."';".
                " script-src 'self' 'nonce-".$this->nonce."';".
                " frame-ancestors 'self';".
                " form-action 'self';"
            );
        }

        public function javascripts_nonce(
            $nonce
        ): string {
            return $this->nonce;
        }

        public function stylesheets_nonce(
            $nonce
        ): string {
            return $this->nonce;
        }
    }
