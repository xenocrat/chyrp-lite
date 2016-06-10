<?php
    /**
     * File: Thumb
     * Generates compressed image thumbnails for uploaded files.
     */

    define('USE_ZLIB', false);

    require_once "common.php";

    if (shorthand_bytes(ini_get("memory_limit")) < 50331648)
        ini_set("memory_limit", "48M");

    if (isset($_SERVER["REQUEST_METHOD"]) and $_SERVER["REQUEST_METHOD"] !== "GET")
        error(__("Error"), __("This resource accepts GET requests only."), null, 405);

    if (empty($_GET['file']))
        error(__("Error"), __("Missing argument."), null, 400);

    $config = Config::current();
    $quality = (int) fallback($_GET["quality"], 80);
    $filename = oneof(trim($_GET['file']), DIR);
    $filepath = uploaded($filename, false);;
    $extension = pathinfo($filename, PATHINFO_EXTENSION);
    $url = uploaded($filename);
    $new_width = (int) fallback($_GET["max_width"], 640);
    $new_height = (int) fallback($_GET["max_height"], 0);

    if (!function_exists("gd_info"))
        exit(header("Location: ".$url)); # GD not installed.

    $gd_info = gd_info();
    preg_match("/\d[\d\.]*/", $gd_info["GD Version"], $gd_version);

    if (version_compare($gd_version[0], "2.0.28", "<"))
        exit(header("Location: ".$url)); # GD version too low.

    if (substr_count($filename, DIR))
        error(__("Error"), __("Malformed URI."), null, 400);

    if (!is_readable($filepath) or !is_file($filepath))
        error(__("Not Found"), __("File not found."), null, 404);

    list($original_width, $original_height, $type, $attr) = getimagesize($filepath);

    $crop_x = 0;
    $crop_y = 0;

    function resize(&$crop_x, &$crop_y, &$new_width, &$new_height, $original_width, $original_height) {
        $xscale = ($new_width > 0) ? $new_width / $original_width : 0 ;
        $yscale = ($new_height > 0) ? $new_height / $original_height : 0 ;

        if ($new_width <= $original_width and $new_height <= $original_height and $xscale == $yscale)
            return;

        if (isset($_GET['square'])) {
            if ( $new_width === 0 )
                $new_width = $new_height;

            if ( $new_height === 0 )
                $new_height = $new_width;

            if($original_width > $original_height) {
                # portrait
                $crop_x = ceil( ($original_width - $original_height) / 2 );
            } else if ($original_height > $original_width) {
                # landscape
                $crop_y = ceil( ($original_height - $original_width) / 2 );
            }

            return;

        } else {

            if ($new_width and !$new_height)
                return $new_height = ($new_width / $original_width) * $original_height;
            elseif (!$new_width and $new_height)
                return $new_width = ($new_height / $original_height) * $original_width;

            if ($xscale != $yscale) {
                if ($original_width * $yscale <= $new_width)
                    $new_width = $original_width * $yscale;

                if ($original_height * $xscale <= $new_height)
                    $new_height = $original_height * $xscale;
            }

            $xscale = ($new_width > 0) ? $new_width / $original_width : 0 ;
            $yscale = ($new_height > 0) ? $new_height / $original_height : 0 ;
    
            if (round($xscale, 3) == round($yscale, 3))
                return;
    
            resize($crop_x, $crop_y, $new_width, $new_height, $original_width, $original_height);
        }
    }

    # Determine the final scale of the thumbnail.
    resize($crop_x, $crop_y, $new_width, $new_height, $original_width, $original_height);

    # If it's already below the maximum, just redirect to it.
    if ($original_width <= $new_width and $original_height <= $new_height)
        exit(header("Location: ".$url));

    $cache_filename = md5($filename.$new_width.$new_height.$quality).".".$extension;
    $cache_file = CACHES_DIR.DIR."thumbs".DIR."thumb_".$cache_filename;

    if (isset($_GET['no_cache']) and $_GET['no_cache'] == "true" and file_exists($cache_file))
        unlink($cache_file);

    # Serve a cache if it exists and the original image has not changed.
    if (file_exists($cache_file) and filemtime($cache_file) > filemtime($filepath)) {
        if (DEBUG)
            error_log("SERVING image thumbnail for ".$filename);

        header("Last-Modified: ".gmdate('D, d M Y H:i:s', filemtime($cache_file)).' GMT');
        header("Content-type: image/".($extension == "jpg" ? "jpeg" : $extension));
        header("Cache-Control: public");
        header("Expires: ".date("r", strtotime("+30 days")));
        header("Content-Disposition: inline; filename=".$cache_filename);
        readfile($cache_file);
        exit;
    }

    # Verify that the image is able to be thumbnailed, and prepare variables used later in the script.
    switch ($type) {
        case IMAGETYPE_GIF:
            if (imagetypes() & IMG_GIF) {
                $image = imagecreatefromgif($filepath);
                $done = "imagegif";
                $mime = "image/gif";
                break;
            }
        case IMAGETYPE_JPEG:
            if (imagetypes() & IMG_JPG) {
                $image = imagecreatefromjpeg($filepath);
                $done = "imagejpeg";
                $mime = "image/jpeg";
                break;
            }
        case IMAGETYPE_PNG:
            if (imagetypes() & IMG_PNG) {
                $image = imagecreatefrompng($filepath);
                $done = "imagepng";
                $mime = "image/png";
                break;
            }
        case IMAGETYPE_BMP:
            if (imagetypes() & IMG_WBMP) {
                $image = imagecreatefromwbmp($filepath);
                $done = "imagewbmp";
                $mime = "image/bmp";
                break;
            }
        default:
            exit(header("Location: ".$url)); # Switch will flow through to here if image type is unsupported.
    }

    if (DEBUG)
        error_log("GENERATING image thumbnail for ".$filename);

    # Create the final resized image.
    $thumbnail = imagecreatetruecolor($new_width, $new_height);

    if ($done == "imagepng")
        imagealphablending($thumbnail, false);

    # if square crop is desired, original dimensions need to be set to square ratio.
    if ( isset($_GET['square']) ) {
        if ($original_width > $original_height) {
            $original_width = $original_height;
        } else if ($original_height > $original_width) {
            $original_height = $original_width;
        }
    }

    imagecopyresampled($thumbnail, $image, 0, 0, $crop_x, $crop_y, $new_width, $new_height, $original_width, $original_height);

    header("Last-Modified: ".gmdate("D, d M Y H:i:s", filemtime($filepath))." GMT");
    header("Content-Type: ".$mime);
    header("Content-Disposition: inline; filename=".$filename.".".$extension);

    if ($done == "imagepng")
        imagesavealpha($thumbnail, true);

    # Generate the cache image.
    if ((!isset($_GET['no_cache']) or $_GET['no_cache'] == "false") and is_writable(CACHES_DIR.DIR."thumbs"))
        if ($done == "imagejpeg")
            $done($thumbnail, $cache_file, $quality);
        else
            $done($thumbnail, $cache_file);

    # Serve the image.
    if ($done == "imagejpeg")
        $done($thumbnail, null, $quality);
    else
        $done($thumbnail);

    # Clear memory and flush the output buffer.
    imagedestroy($image);
    imagedestroy($thumbnail);

    ob_end_flush();
