<?php
    class EasyEmbed extends Modules {
        public function __init(
        ): void {
            # Replace comment codes before Markdown filtering (priority 5).
            $this->setPriority("markup_text", 4);
        }

        public function markup_text(
            $text
        ): string {
            $urls = array(
                # YouTube:
                    '|<!--[^>]*youtube.com/embed/([a-z0-9_\-]{11})[^>]*-->|i'
                =>  '<iframe class="content_embed youtube_embed" src="'.
                    'https://www.youtube.com/embed/$1"'.
                    ' title="'.
                    __("Embedded content from YouTube", "easy_embed").
                    '" allowfullscreen loading="lazy"></iframe>',

                    '|<!--[^>]*youtube.com/watch\?v=([a-z0-9_\-]{11})[^>]*-->|i'
                =>  '<iframe class="content_embed youtube_embed" src="'.
                    'https://www.youtube.com/embed/$1"'.
                    ' title="'.
                    __("Embedded content from YouTube", "easy_embed").
                    '" allowfullscreen loading="lazy"></iframe>',

                    '|<!--[^>]*youtu.be/([a-z0-9_\-]{11})[^>]*-->|i'
                =>  '<iframe class="content_embed youtube_embed" src="'.
                    'https://www.youtube.com/embed/$1"'.
                    ' title="'.
                    __("Embedded content from YouTube", "easy_embed").
                    '" allowfullscreen loading="lazy"></iframe>',

                # Vimeo:
                    '|<!--[^>]*player.vimeo.com/video/([0-9]{9})[^>]*-->|i'
                =>  '<iframe class="content_embed vimeo_embed" src="'.
                    'https://player.vimeo.com/video/$1"'.
                    ' title="'.
                    __("Embedded content from Vimeo", "easy_embed").
                    '" allowfullscreen loading="lazy"></iframe>',

                    '|<!--[^>]*vimeo.com/([0-9]{9})[^>]*-->|i'
                =>  '<iframe class="content_embed vimeo_embed" src="'.
                    'https://player.vimeo.com/video/$1"'.
                    ' title="'.
                    __("Embedded content from Vimeo", "easy_embed").
                    '" allowfullscreen loading="lazy"></iframe>',

                # Twitch:
                    '|<!--[^>]*player.twitch.tv/\?video=v([0-9]{9})[^>]*-->|i'
                =>  '<iframe class="content_embed twitch_embed" src="'.
                    'https://player.twitch.tv/?video=v$1"'.
                    ' title="'.
                    __("Embedded content from Twitch", "easy_embed").
                    '" allowfullscreen loading="lazy"></iframe>',

                    '|<!--[^>]*twitch.tv/[^/]+/v/([0-9]{9})[^>]*-->|i'
                =>  '<iframe class="content_embed twitch_embed" src="'.
                    'https://player.twitch.tv/?video=v$1"'.
                    ' title="'.
                    __("Embedded content from Twitch", "easy_embed").
                    '" allowfullscreen loading="lazy"></iframe>',

                # Internet Archive:
                    '|<!--[^>]*archive.org/embed/([a-z0-9_\-]+)[^>]*-->|i'
                =>  '<iframe class="content_embed archiveorg_embed" src="'.
                    'https://archive.org/embed/$1"'.
                    ' title="'.
                    __("Embedded content from Internet Archive", "easy_embed").
                    '" allowfullscreen loading="lazy"></iframe>',

                    '|<!--[^>]*archive.org/details/([a-z0-9_\-]+)[^>]*-->|i'
                =>  '<iframe class="content_embed archiveorg_embed" src="'.
                    'https://archive.org/embed/$1"'.
                    ' title="'.
                    __("Embedded content from Internet Archive", "easy_embed").
                    '" allowfullscreen loading="lazy"></iframe>',

                # Spotify:
                    '|<!--[^>]*open.spotify.com/embed/([a-z0-9/]+)[^>]*-->|i'
                =>  '<iframe class="content_embed spotify_embed" src="'.
                    'https://open.spotify.com/embed/$1"'.
                    ' title="'.
                    __("Embedded content from Spotify", "easy_embed").
                    '" allowfullscreen loading="lazy"></iframe>',

                # Bandcamp:
                    '|<!--[^>]*bandcamp.com/EmbeddedPlayer/([a-z0-9/=]*minimal=true[a-z0-9/=]*)[^>]*-->|i'
                =>  '<iframe class="content_embed bandcamp_m_embed" src="'.
                    'https://bandcamp.com/EmbeddedPlayer/$1"'.
                    ' title="'.
                    __("Embedded content from Bandcamp", "easy_embed").
                    '" allowfullscreen loading="lazy"></iframe>',

                    '|<!--[^>]*bandcamp.com/EmbeddedPlayer/([a-z0-9/=]*size=small[a-z0-9/=]*)[^>]*-->|i'
                =>  '<iframe class="content_embed bandcamp_s_embed" src="'.
                    'https://bandcamp.com/EmbeddedPlayer/$1"'.
                    ' title="'.
                    __("Embedded content from Bandcamp", "easy_embed").
                    '" allowfullscreen loading="lazy"></iframe>',

                    '|<!--[^>]*bandcamp.com/EmbeddedPlayer/([a-z0-9/=]*size=large[a-z0-9/=]*)[^>]*-->|i'
                =>  '<iframe class="content_embed bandcamp_l_embed" src="'.
                    'https://bandcamp.com/EmbeddedPlayer/$1"'.
                    ' title="'.
                    __("Embedded content from Bandcamp", "easy_embed").
                    '" allowfullscreen loading="lazy"></iframe>'
            );

            return preg_replace(
                array_keys($urls),
                array_values($urls),
                $text
            );
        }
    }
