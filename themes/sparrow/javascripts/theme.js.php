<?php
    define('JAVASCRIPT', true);
    require_once "../../../includes/common.php";
    error_reporting(0);
    header("Content-Type: application/x-javascript");
?>
<!-- --><script>

// Obfuscated mailto
function mailTo(domain, recipient) {
    location.assign(('mailto:' + recipient + '@' + domain));
    return true;
}

// Prevent tabbing through yearly archive post previews
$(document).ready(function() {
	$("article.post.archive *").not(".archive_post_link").attr("tabindex", "-1")
});

<!-- --></script>
