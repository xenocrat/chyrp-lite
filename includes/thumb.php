<?php
    /**
     * File: thumb
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
    $thumbnail_width = abs((int) fallback($_GET["max_width"], 640));
    $thumbnail_height = abs((int) fallback($_GET["max_height"], 0));

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

    if ($thumbnail_width == 0 and $thumbnail_height == 0)
        error(__("Error"), __("Maximum size cannot be zero."), null, 422);

    function resize_thumb(&$crop_x, &$crop_y, &$thumbnail_width, &$thumbnail_height, &$original_width, &$original_height) {
        $scale_x = ($thumbnail_width > 0) ? $thumbnail_width / $original_width : 0 ;
        $scale_y = ($thumbnail_height > 0) ? $thumbnail_height / $original_height : 0 ;

        if (!empty($_GET['square'])) {
            if ($thumbnail_width > $thumbnail_height)
                $thumbnail_height = $thumbnail_width;

            if ($thumbnail_height > $thumbnail_width)
                $thumbnail_width = $thumbnail_height;

            # Portrait orientation.
            if ($original_width > $original_height) {
                $crop_x = round(($original_width - $original_height) / 2);
                $original_width = $original_height;
            }

            # Landscape orientation.
            if ($original_height > $original_width) {
                $crop_y = round(($original_height - $original_width) / 2);
                $original_height = $original_width;
            }

            return;
        }

        if ($thumbnail_height == 0) {
            $thumbnail_height = round(($thumbnail_width / $original_width) * $original_height);
            return;
        }

        if ($thumbnail_width == 0) {
            $thumbnail_width = round(($thumbnail_height / $original_height) * $original_width);
            return;
        }

        # Recompute to retain aspect ratio and stay within bounds.
        if ($scale_x != $scale_y) {
            if ($original_width * $scale_y <= $thumbnail_width) {
                $thumbnail_width = round($original_width * $scale_y);
                return;
            }

            if ($original_height * $scale_x <= $thumbnail_height) {
                $thumbnail_height = round($original_height * $scale_x);
                return;
            }
        }
    }

    # Fetch original image metadata.
    list($original_width, $original_height, $type, $attr) = getimagesize($filepath);

    $crop_x = 0;
    $crop_y = 0;
    $quality = ($quality > 100) ? 100 : $quality ;

    # Call our function to determine the final scale of the thumbnail.
    resize_thumb($crop_x, $crop_y, $thumbnail_width, $thumbnail_height, $original_width, $original_height);

    # Redirect to the original if the size is already less than requested.
    if ($original_width <= $thumbnail_width and $original_height <= $thumbnail_height and empty($_GET['square']))
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

    $cache_filename = md5($filename.$thumbnail_width.$thumbnail_height.$quality).".".$extension;
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
            error_log("GENERATING image thumbnail for ".$filename);

        # Create the thumbnail.
        $thumbnail = imagecreatetruecolor($thumbnail_width, $thumbnail_height);

        if ($function == "imagepng") {
            imagealphablending($thumbnail, false);
            imagesavealpha($thumbnail, true);
            $quality = 10 - (($quality > 0) ? ceil($quality / 10) : 1);
        }

        # Do the crop and resize.
        imagecopyresampled($thumbnail,
                           $original,
                           0,
                           0,
                           $crop_x,
                           $crop_y,
                           $thumbnail_width,
                           $thumbnail_height,
                           $original_width,
                           $original_height);

        # Create the thumbnail.
        $result = ($function == "imagejpeg" or $function == "imagepng") ?
            $function($thumbnail, $cache_filepath, $quality) : $function($thumbnail, $cache_filepath) ;

        if ($result === false)
            error(__("Error"), __("Failed to create image thumbnail."));

        # Destroy resources.
        imagedestroy($original);
        imagedestroy($thumbnail);
    }

    if (isset($cache_filepath)) {
        if (DEBUG)
            error_log("SERVING image thumbnail for ".$filename);

        readfile($cache_filepath);
    }

    ob_end_flush();
