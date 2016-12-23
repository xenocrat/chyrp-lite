<?php
    class EasyEmbed extends Modules {
        public function __init() {
            # Replace comment codes before markup modules filters them.
            $this->setPriority("markup_text", 4);
        }

        public function markup_text($text) {
            if (!is_string($text))
                return $text;

            $urls = array('|<!--[^>]*youtube.com/watch\?v=([a-z0-9_\-]{11})[^>]*-->|i'      => 'https://www.youtube.com/embed/$1',
                          '|<!--[^>]*youtu.be/([a-z0-9_\-]{11})[^>]*-->|i'                  => 'https://www.youtube.com/embed/$1',
                          '|<!--[^>]*vimeo.com/([0-9]{9})[^>]*-->|i'                        => 'https://player.vimeo.com/video/$1',
                          '|<!--[^>]*twitch.tv/[^/]+/v/([0-9]{9})[^>]*-->|i'                => 'https://player.twitch.tv/?video=v$1',
                          '|<!--[^>]*giantbomb.com/videos/[^/]+/[0-9]+-([0-9]+)/[^>]*-->|i' => 'http://www.giantbomb.com/videos/embed/$1/',
                          '|<!--[^>]*beam.pro/([a-z0-9_\-]+)[^>]*-->|i'                     => 'https://beam.pro/embed/player/$1');

            foreach ($urls as $view => &$embed)
                $embed = '<iframe class="video_embed" src="'.fix($embed, true).'" allowfullscreen></iframe>';

            return preg_replace(array_keys($urls), array_values($urls), $text);
        }
    }
