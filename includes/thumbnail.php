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
    $filepath = uploaded($filename, false);
    $ext = pathinfo($filename, PATHINFO_EXTENSION);
    $url = uploaded($filename);
    $thumb_w = abs((int) fallback($_GET["max_width"], 640));
    $thumb_h = abs((int) fallback($_GET["max_height"], 0));

    if (!function_exists("gd_info"))
        redirect($url);

    if (!is_readable($filepath) or !is_file($filepath))
        show_404(__("Not Found"), __("File not found."));

    if ($thumb_w == 0 and $thumb_h == 0)
        error(__("Error"), __("Maximum size cannot be zero."), null, 422);

    # Respond to Last-Modified-Since so the user agent will use cache.
    if (isset($_SERVER['HTTP_IF_MODIFIED_SINCE'])) {
        $lastmod = strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']);

        if ($lastmod >= filemtime($filepath)) {
            header($_SERVER['SERVER_PROTOCOL']." 304 Not Modified");
            header("Cache-Control: public");
            header("Pragma: public");
            header("Expires: ".date("r", now("+30 days")));
            exit;
        }
    }

    function resize_thumb(&$crop_x, &$crop_y, &$thumb_w, &$thumb_h, &$orig_w, &$orig_h) {
        $scale_x = ($thumb_w > 0) ? $thumb_w / $orig_w : 0 ;
        $scale_y = ($thumb_h > 0) ? $thumb_h / $orig_h : 0 ;

        if (!empty($_GET['square'])) {
            if ($thumb_w > $thumb_h)
                $thumb_h = $thumb_w;

            if ($thumb_h > $thumb_w)
                $thumb_w = $thumb_h;

            # Portrait orientation.
            if ($orig_w > $orig_h) {
                $crop_x = round(($orig_w - $orig_h) / 2);
                $orig_w = $orig_h;
            }

            # Landscape orientation.
            if ($orig_h > $orig_w) {
                $crop_y = round(($orig_h - $orig_w) / 2);
                $orig_h = $orig_w;
            }

            return;
        }

        if ($thumb_h == 0) {
            $thumb_h = round(($thumb_w / $orig_w) * $orig_h);
            return;
        }

        if ($thumb_w == 0) {
            $thumb_w = round(($thumb_h / $orig_h) * $orig_w);
            return;
        }

        # Recompute to retain aspect ratio and stay within bounds.
        if ($scale_x != $scale_y) {
            if ($orig_w * $scale_y <= $thumb_w) {
                $thumb_w = round($orig_w * $scale_y);
                return;
            }

            if ($orig_h * $scale_x <= $thumb_h) {
                $thumb_h = round($orig_h * $scale_x);
                return;
            }
        }
    }

    # Fetch original image metadata.
    list($orig_w, $orig_h, $type, $attr) = getimagesize($filepath);

    $crop_x = 0;
    $crop_y = 0;
    $quality = ($quality > 100) ? 100 : $quality ;

    # Call our function to determine the final scale of the thumbnail.
    resize_thumb($crop_x, $crop_y, $thumb_w, $thumb_h, $orig_w, $orig_h);

    # Redirect to the original if the size is already less than requested.
    if ($orig_w <= $thumb_w and $orig_h <= $thumb_h and empty($_GET['square']))
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

    $cache_fn = md5($filename.$thumb_w.$thumb_h.$quality).".".$ext;
    $cache_fp = (CACHE_THUMBS) ? CACHES_DIR.DIR."thumbs".DIR.$cache_fn : null ;

    header("Last-Modified: ".date("r", filemtime($filepath)));
    header("Content-Type: ".$media_type);
    header("Cache-Control: public");
    header("Pragma: public");
    header("Expires: ".date("r", now("+30 days")));
    header("Content-Disposition: inline; filename=\"".addslashes($cache_fn)."\"");

    # Create a thumbnail if caching is disabled or no file exists.
    if (!isset($cache_fp) or !file_exists($cache_fp)) {
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
            error_log("CREATE image ".$cache_fn);

        # Create the thumbnail.
        $thumb = imagecreatetruecolor($thumb_w, $thumb_h);

        if ($function == "imagepng") {
            imagealphablending($thumb, false);
            imagesavealpha($thumb, true);
            $quality = 10 - (($quality > 0) ? ceil($quality / 10) : 1);
        }

        if ($function == "imagejpeg") {
            imageinterlace($thumb, true);
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
                           $orig_w,
                           $orig_h);

        # Create the thumbnail file - outputs directly if caching is disabled.
        $result = ($function == "imagejpeg" or $function == "imagepng") ?
            $function($thumb, $cache_fp, $quality) : $function($thumb, $cache_fp) ;

        if ($result === false)
            error(__("Error"), __("Failed to create image thumbnail."));

        # Destroy resources.
        imagedestroy($original);
        imagedestroy($thumb);
    }

    # Serve a file previously or newly created.
    if (isset($cache_fp)) {
        if (DEBUG)
            error_log("SERVE image ".$cache_fn);

        readfile($cache_fp);
    }

    ob_end_flush();
