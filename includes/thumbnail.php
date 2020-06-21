<?php
    /**
     * File: thumbnail
     * Generates compressed image thumbnails for uploaded files.
     */

    define('USE_ZLIB', false);

    require_once "common.php";

    if (shorthand_bytes(ini_get("memory_limit")) < 50331648)
        ini_set("memory_limit", "48M");

    if (isset($_SERVER['REQUEST_METHOD']) and $_SERVER['REQUEST_METHOD'] !== "GET")
        error(__("Error"), __("This resource accepts GET requests only."), null, 405);

    if (empty($_GET['file']))
        error(__("Error"), __("Missing argument."), null, 400);

    if (!$visitor->group->can("view_site"))
        show_403(__("Access Denied"), __("You are not allowed to view this site."));

    $config = Config::current();
    $quality = abs((int) fallback($_GET["quality"], 80));
    $filename = str_replace(DIR, "", $_GET['file']);
    $filepath = uploaded($filename, false);;
    $extension = pathinfo($filename, PATHINFO_EXTENSION);
    $url = uploaded($filename);
    $thumb_w = abs((int) fallback($_GET["max_width"], 640));
    $thumb_h = abs((int) fallback($_GET["max_height"], 0));

    # GD library is not available.
    if (!function_exists("gd_info"))
        redirect($url);

    $gd_info = gd_info();
    preg_match("/\d[\d\.]*/", $gd_info["GD Version"], $gd_version);

    # GD version too low for our script.
    if (version_compare($gd_version[0], "2.0.28", "<"))
        redirect($url);

    if (!is_readable($filepath) or !is_file($filepath))
        show_404(__("Not Found"), __("File not found."));

    if ($thumb_w == 0 and $thumb_h == 0)
        error(__("Error"), __("Maximum size cannot be zero."), null, 422);

    function resize_thumb(&$crop_x, &$crop_y, &$thumb_w, &$thumb_h, &$original_w, &$original_h) {
        $scale_x = ($thumb_w > 0) ? $thumb_w / $original_w : 0 ;
        $scale_y = ($thumb_h > 0) ? $thumb_h / $original_h : 0 ;

        if (!empty($_GET['square'])) {
            if ($thumb_w > $thumb_h)
                $thumb_h = $thumb_w;

            if ($thumb_h > $thumb_w)
                $thumb_w = $thumb_h;

            # Portrait orientation.
            if ($original_w > $original_h) {
                $crop_x = round(($original_w - $original_h) / 2);
                $original_w = $original_h;
            }

            # Landscape orientation.
            if ($original_h > $original_w) {
                $crop_y = round(($original_h - $original_w) / 2);
                $original_h = $original_w;
            }

            return;
        }

        if ($thumb_h == 0) {
            $thumb_h = round(($thumb_w / $original_w) * $original_h);
            return;
        }

        if ($thumb_w == 0) {
            $thumb_w = round(($thumb_h / $original_h) * $original_w);
            return;
        }

        # Recompute to retain aspect ratio and stay within bounds.
        if ($scale_x != $scale_y) {
            if ($original_w * $scale_y <= $thumb_w) {
                $thumb_w = round($original_w * $scale_y);
                return;
            }

            if ($original_h * $scale_x <= $thumb_h) {
                $thumb_h = round($original_h * $scale_x);
                return;
            }
        }
    }

    # Fetch original image metadata.
    list($original_w, $original_h, $type, $attr) = getimagesize($filepath);

    $crop_x = 0;
    $crop_y = 0;
    $quality = ($quality > 100) ? 100 : $quality ;

    # Call our function to determine the final scale of the thumbnail.
    resize_thumb($crop_x, $crop_y, $thumb_w, $thumb_h, $original_w, $original_h);

    # Redirect to the original if the size is already less than requested.
    if ($original_w <= $thumb_w and $original_h <= $thumb_h and empty($_GET['square']))
        redirect($url);

    # Determine the media type.
    switch ($type) {
        case IMAGETYPE_GIF:
            $media_type = "image/gif";
            break;
        case IMAGETYPE_JPEG:
            $media_type = "image/jpeg";
            break;
        case IMAGETYPE_PNG:
            $media_type = "image/png";
            break;
        case IMAGETYPE_BMP:
            $media_type = "image/bmp";
            break;
        default:
            $media_type = "application/octet-stream";
    }

    $cache_filename = md5($filename.$thumb_w.$thumb_h.$quality).".".$extension;
    $cache_filepath = (CACHE_THUMBS) ? CACHES_DIR.DIR."thumbs".DIR."thumb_".$cache_filename : null ;

    header("Last-Modified: ".date("r", filemtime($filepath)));
    header("Content-Type: ".$media_type);
    header("Cache-Control: public");
    header("Expires: ".date("r", strtotime("+30 days")));
    header("Content-Disposition: inline; filename=\"".addslashes($cache_filename)."\"");

    if (!isset($cache_filepath) or
        !file_exists($cache_filepath) or
        !(filemtime($cache_filepath) > filemtime($filepath))) {
        # Verify the media type is supported and prepare the original.
        switch ($type) {
            case IMAGETYPE_GIF:
                if (imagetypes() & IMG_GIF) {
                    $original = imagecreatefromgif($filepath);
                    $function = "imagegif";
                    break;
                }
            case IMAGETYPE_JPEG:
                if (imagetypes() & IMG_JPG) {
                    $original = imagecreatefromjpeg($filepath);
                    $function = "imagejpeg";
                    break;
                }
            case IMAGETYPE_PNG:
                if (imagetypes() & IMG_PNG) {
                    $original = imagecreatefrompng($filepath);
                    $function = "imagepng";
                    break;
                }
            case IMAGETYPE_BMP:
                if (imagetypes() & IMG_WBMP) {
                    $original = imagecreatefromwbmp($filepath);
                    $function = "imagewbmp";
                    break;
                }
            default:
                redirect($url); # Redirect if type is unsupported.
        }

        if (DEBUG)
            error_log("CREATE image thumbnail for ".$filename);

        # Create the thumbnail.
        $thumb = imagecreatetruecolor($thumb_w, $thumb_h);

        if ($function == "imagepng") {
            imagealphablending($thumb, false);
            imagesavealpha($thumb, true);
            $quality = 10 - (($quality > 0) ? ceil($quality / 10) : 1);
        }

        # Do the crop and resize.
        imagecopyresampled($thumb,
                           $original,
                           0,
                           0,
                           $crop_x,
                           $crop_y,
                           $thumb_w,
                           $thumb_h,
                           $original_w,
                           $original_h);

        # Create the thumbnail.
        $result = ($function == "imagejpeg" or $function == "imagepng") ?
            $function($thumb, $cache_filepath, $quality) : $function($thumb, $cache_filepath) ;

        if ($result === false)
            error(__("Error"), __("Failed to create image thumbnail."));

        # Destroy resources.
        imagedestroy($original);
        imagedestroy($thumb);
    }

    if (isset($cache_filepath)) {
        if (DEBUG)
            error_log("SERVE image thumbnail for ".$filename);

        readfile($cache_filepath);
    }

    ob_end_flush();
