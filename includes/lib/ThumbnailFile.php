<?php
   /**
    * Class: ThumbnailFile
    * Creates and serves compressed image thumbnails.
    */
    class ThumbnailFile {
        # Integer: $orig_w
        # Width of the original image.
        private $orig_w = 0;

        # Integer: $orig_h
        # Height of the original image.
        private $orig_h = 0;

        # Integer: $thumb_w
        # Width calculated for the thumbnail.
        private $thumb_w = 1;

        # Integer: $thumb_h
        # Height calculated for the thumbnail.
        private $thumb_h = 1;

        # Integer: $crop_x
        # Horizontal offset in pixels for cropping.
        private $crop_x = 0;

        # Integer: $crop_y
        # Vertical offset in pixels for cropping.
        private $crop_y = 0;

        # Integer: $quality
        # Quality factor for the thumbnail file.
        private $quality = 80;

        # Integer: $orientation
        # EXIF orientation tag value.
        private $orientation = 1;

        # Variable: $type
        # The original image type detected by GD library.
        private $type = 0;

        # Boolean: $square
        # Square thumbnail image requested?
        private $square = false;

        # String: $source
        # The source filepath supplied to the constructor.
        private $source = null;

        # String: $name
        # The unique destination name generated from parameters.
        private $name = null;

        # String: $destination
        # The destination filepath.
        private $destination = null;

        # Boolean: $creatable
        # Can a thumbnail be created?
        private $creatable = null;

        /**
         * Function: __construct
         * Receives the source filename and requested thumbnail parameters.
         *
         * Parameters:
         *     $filename - Filename relative to the uploads directory.
         *     $thumb_w - Requested thumbnail width (0 = auto).
         *     $thumb_h - Requested thumbnail height (0 = auto).
         *     $quality - Quality factor for the thumbnail file.
         *     $square - Create a square crop of the original image?
         */
        public function __construct(
            $filename,
            $thumb_w,
            $thumb_h,
            $quality,
            $square
        ) {
            $this->thumb_w = (int) $thumb_w;
            $this->thumb_h = (int) $thumb_h;
            $this->quality = (int) $quality;
            $this->square = (bool) $square;

            $filepath = uploaded($filename, false);
            $image_info = @getimagesize($filepath);

            if ($image_info === false)
                return;

            $this->source = $filepath;
            $this->type = $image_info[2];

            if ($image_info[0] != 0)
                $this->orig_w = $image_info[0];

            if ($image_info[1] != 0)
                $this->orig_h = $image_info[1];

            if ($this->thumb_w == 0 and $this->thumb_h == 0) {
                $this->thumb_w = 1;
                $this->thumb_h = 1;
            }

            if ($this->quality > 100 or $this->quality < 0)
                $this->quality = 80;

            if (function_exists("exif_read_data")) {
                $exif = @exif_read_data($filepath);

                if ($exif !== false) {
                    if (isset($exif["Orientation"]))
                        $this->orientation = (int) $exif["Orientation"];

                    if ($this->orientation >= 5) {
                        $orig_w = $this->orig_w;
                        $orig_h = $this->orig_h;
                        $this->orig_w = $orig_h;
                        $this->orig_h = $orig_w;
                    }
                }
            }

            $this->resize();
            $this->destination = CACHES_DIR.DIR."thumbs".DIR.$this->name();
        }

        /**
         * Function: upscaling
         * Will the thumbnail be larger than the original?
         */
        public function upscaling(
        ): bool {
            return (
                ($this->thumb_w == 0 or $this->orig_w <= $this->thumb_w) and
                ($this->thumb_h == 0 or $this->orig_h <= $this->thumb_h) and
                (!$this->square or $this->orig_w == $this->orig_h)
            );
        }

        /**
         * Function: creatable
         * Can the thumbnail file be created?
         */
        public function creatable(
        ): bool {
            if (isset($this->creatable))
                return $this->creatable;

            if (!isset($this->source))
                return $this->creatable = false;

            if (!function_exists("gd_info"))
                return $this->creatable = false;

            $imagetypes = imagetypes();

            if ($this->type == IMAGETYPE_GIF and ($imagetypes & IMG_GIF)) {
                # If a square crop is requested, animation is irrelevant.
                if ($this->square)
                    return $this->creatable = true;

                # Probe the file and return false if GIF89a with animation
                # because GD will only operate on the first frame of the file.

                $data = @fopen(filename:$this->source, mode:"rb");

                if ($data === false)
                    return $this->creatable = false;

                $count = 0;
                $contents = "";

                # Count the number of graphic control extension blocks
                # followed by an image descriptor or another extension:
                #     > Graphic Control Extension
                #         > Extension Introducer (0x21)
                #         > Graphic Control Label (0xf9)
                #         > Block Size (0x04)
                #         > <Packed Fields> (1 byte)
                #         > Delay Time (2 bytes)
                #         > Transparent Color Index (1 byte)
                #         > Block Terminator (0x00)
                #    > Image Descriptor / Graphic Control Extension
                #         > Extension Introducer (0x2c / 0x21)
                #
                # See also:
                #     https://www.w3.org/Graphics/GIF/spec-gif89a.txt
                #
                while (!feof($data) and $count < 2) {
                    $contents.= fread($data, 102400);

                    $count = (int) preg_match_all(
                        "/\\x21\\xf9\\x04.{4}\\x00(\\x2c|\\x21)/s",
                        $contents
                    );
                }

                return $this->creatable = ($count < 2) ?
                    true :
                    false ;
            }

            if ($this->type == IMAGETYPE_JPEG and ($imagetypes & IMG_JPEG))
                return $this->creatable = true;

            if ($this->type == IMAGETYPE_PNG and ($imagetypes & IMG_PNG))
                return $this->creatable = true;

            if ($this->type == IMAGETYPE_WEBP and ($imagetypes & IMG_WEBP)) {
                # Probe the file and return false if WEBP VP8X with animation
                # because GD will throw a PHP Fatal error on imagecreatefromwebp().

                $data = @file_get_contents(filename:$this->source, length:21);

                if ($data === false or strlen($data) < 21)
                    return $this->creatable = false;

                # Unpack the following data from the first 21 bytes of the WEBP file:
                #     > File header (12 bytes)
                #     > First chunk:
                #         > Chunk header (4 bytes)
                #         > Chunk size (4 bytes)
                #         > Chunk payload (1 byte only)
                #
                # See also:
                #     https://developers.google.com/speed/webp/docs/riff_container
                #
                $header = unpack(
                    "A4riff/Vfilesize/A4webp/A4header/Vsize/Cpayload",
                    $data
                );

                # Discover if VP8X header is present and animation bit is set.
                if ($header['header'] == "VP8X" and $header['payload'] & 0x02)
                    return $this->creatable = false;

                return $this->creatable = true;
            }

            if (!defined('IMAGETYPE_AVIF'))
                return $this->creatable = false;

            if (!defined('IMG_AVIF'))
                return $this->creatable = false;

            if ($this->type == IMAGETYPE_AVIF and ($imagetypes & IMG_AVIF))
                return $this->creatable = true;

            return $this->creatable = false;
        }

        /**
         * Function: extension
         * Returns the correct extension for the image.
         */
        public function extension(
        ): string|false {
            return image_type_to_extension($this->type);
        }

        /**
         * Function: mime_type
         * Returns the correct MIME type for the image.
         */
        public function mime_type(
        ): string|false {
            return image_type_to_mime_type($this->type);
        }

        /**
         * Function: name
         * Generates and returns a unique name for the thumbnail file.
         */
        public function name(
        ): string|false {
            if (isset($this->name))
                return $this->name;

            if (!isset($this->source))
                return false;

            $hash = md5(
                basename($this->source).
                $this->thumb_w.
                $this->thumb_h.
                $this->quality
            );

            return $this->name = $hash.$this->extension();
        }

        /**
         * Function: create
         * Creates a thumbnail file using the supplied parameters.
         *
         * Parameters:
         *     $overwrite - Overwrite an existing thumbnail file?
         */
        public function create(
            $overwrite = false
        ): bool {
            if (!$this->creatable())
                return false;

            # Check if a fresh image thumbnail already exists.
            if (
                !$overwrite and
                file_exists($this->destination) and
                filemtime($this->destination) >= filemtime($this->source)
            ) {
                if (DEBUG)
                    error_log("IMAGE fresh ".$this->destination);

                return true;
            }

            $thumb = imagecreatetruecolor($this->thumb_w, $this->thumb_h);

            if ($thumb === false)
                error(
                    __("Error"),
                    __("Failed to create image thumbnail.")
                );

            switch ($this->type) {
                case IMAGETYPE_GIF:
                    $original = @imagecreatefromgif($this->source);
                    break;
                case IMAGETYPE_JPEG:
                    $original = @imagecreatefromjpeg($this->source);
                    imageinterlace($thumb, true);
                    break;
                case IMAGETYPE_PNG:
                    $original = @imagecreatefrompng($this->source);
                    imagealphablending($thumb, false);
                    imagesavealpha($thumb, true);
                    break;
                case IMAGETYPE_WEBP:
                    $original = @imagecreatefromwebp($this->source);
                    imagealphablending($thumb, false);
                    imagesavealpha($thumb, true);
                    break;
                case IMAGETYPE_AVIF:
                    $original = @imagecreatefromavif($this->source);
                    imagealphablending($thumb, false);
                    imagesavealpha($thumb, true);
                    break;
            }

            if ($original === false)
                error(
                    __("Error"),
                    __("Failed to create image thumbnail.")
                );

            if ($this->orientation > 1) {
                # Transform the original image to correct orientation:
                #####################################################
                # 1          #  2         # 3          # 4          #
                #            #            #            #            #
                # XXXXXXX    #    XXXXXXX #         XX # XX         #
                # XX         #         XX #         XX # XX         #
                # XXXXX      #      XXXXX #      XXXXX # XXXXX      #
                # XX         #         XX #         XX # XX         #
                # XX         #         XX #    XXXXXXX # XXXXXXX    #
                #            #            #            #            #
                #####################################################
                # 5          # 6          # 7          # 8          #
                #            #            #            #            #
                # XXXXXXXXXX #            #            # XXXXXXXXXX #
                # XX  XX     # XX         #         XX #     XX  XX #
                # XX  XX     # XX  XX     #     XX  XX #     XX  XX #
                # XX         # XX  XX     #     XX  XX #         XX #
                #            # XXXXXXXXXX # XXXXXXXXXX #            #
                #            #            #            #            #
                #####################################################

                switch ($this->orientation) {
                    case 2:
                        imageflip($original, IMG_FLIP_HORIZONTAL);
                        break;
                    case 3:
                        $original = imagerotate($original, 180, 0);
                        break;
                    case 4:
                        imageflip($original, IMG_FLIP_VERTICAL);
                        break;
                    case 5:
                        imageflip($original, IMG_FLIP_VERTICAL);
                        $original = imagerotate($original, 270, 0);
                        break;
                    case 6:
                        $original = imagerotate($original, 270, 0);
                        break;
                    case 7:
                        imageflip($original, IMG_FLIP_VERTICAL);
                        $original = imagerotate($original, 90, 0);
                        break;
                    case 8:
                        $original = imagerotate($original, 90, 0);
                        break;
                }

                if ($original === false)
                    error(
                        __("Error"),
                        __("Failed to create image thumbnail.")
                    );
            }

            # Do the crop and resize.
            imagecopyresampled(
                $thumb,
                $original,
                0,
                0,
                $this->crop_x,
                $this->crop_y,
                $this->thumb_w,
                $this->thumb_h,
                $this->orig_w,
                $this->orig_h
            );

            # Create the thumbnail file.
            switch ($this->type) {
                case IMAGETYPE_GIF:
                    $result = imagegif(
                        $thumb,
                        $this->destination
                    );
                    break;
                case IMAGETYPE_JPEG:
                    $result = imagejpeg(
                        $thumb,
                        $this->destination,
                        $this->quality
                    );
                    break;
                case IMAGETYPE_PNG:
                    $result = imagepng(
                        $thumb,
                        $this->destination
                    );
                    break;
                case IMAGETYPE_WEBP:
                    $result = imagewebp(
                        $thumb,
                        $this->destination,
                        $this->quality
                    );
                    break;
                case IMAGETYPE_AVIF:
                    $result = imageavif(
                        $thumb,
                        $this->destination,
                        $this->quality
                    );
                    break;
            }

            imagedestroy($thumb);
            imagedestroy($original);

            if ($result === false)
                error(
                    __("Error"),
                    __("Failed to create image thumbnail.")
                );

            if (DEBUG)
                error_log("IMAGE created ".$this->destination);

            return true;
        }

        /**
         * Function: serve
         * Serves a thumbnail file with correct Content-Type header.
         */
        public function serve(
        ): bool {
            if (!file_exists($this->destination))
                return false;

            header("Content-Type: ".$this->mime_type());
            readfile($this->destination);

            if (DEBUG)
                error_log("IMAGE served ".$this->destination);

            return true;
        }

        /**
         * Function: resize
         * Computes the final dimensions based on supplied parameters.
         */
        private function resize(
        ): void {
            $scale_x = ($this->thumb_w > 0) ?
                $this->thumb_w / $this->orig_w :
                0 ;

            $scale_y = ($this->thumb_h > 0) ?
                $this->thumb_h / $this->orig_h :
                0 ;

            if ($this->square) {
                if ($this->thumb_w > $this->thumb_h)
                    $this->thumb_h = $this->thumb_w;

                if ($this->thumb_h > $this->thumb_w)
                    $this->thumb_w = $this->thumb_h;

                # Portrait orientation.
                if ($this->orig_w > $this->orig_h) {
                    $this->crop_x = round(
                        ($this->orig_w - $this->orig_h) / 2
                    );
                    $this->orig_w = $this->orig_h;
                }

                # Landscape orientation.
                if ($this->orig_h > $this->orig_w) {
                    $this->crop_y = round(
                        ($this->orig_h - $this->orig_w) / 2
                    );
                    $this->orig_h = $this->orig_w;
                }

                return;
            }

            if ($this->thumb_h == 0) {
                $this->thumb_h = round(
                    ($this->thumb_w / $this->orig_w) * $this->orig_h
                );
                return;
            }

            if ($this->thumb_w == 0) {
                $this->thumb_w = round(
                    ($this->thumb_h / $this->orig_h) * $this->orig_w
                );
                return;
            }

            # Recompute to retain aspect ratio and stay within bounds.
            if ($scale_x != $scale_y) {
                if ($this->orig_w * $scale_y <= $this->thumb_w) {
                    $this->thumb_w = round($this->orig_w * $scale_y);
                    return;
                }

                if ($this->orig_h * $scale_x <= $this->thumb_h) {
                    $this->thumb_h = round($this->orig_h * $scale_x);
                    return;
                }
            }
        }
    }
