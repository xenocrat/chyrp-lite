<?php
    define('JAVASCRIPT', true);
    require_once "../../../includes/common.php";
    error_reporting(0);
    header("Content-Type: application/javascript");
?>
<!-- --><script>

// Obfuscated mailto
function mailTo(domain, recipient) {
    location.assign(('mailto:' + recipient + '@' + domain));
    return true;
}

// Password helpers
$(document).ready(function() {
    $("input[type='password']#password1, input[type='password']#password2").keyup(function(e) {
        if ( $("input[type='password']#password1").val() !== $("input[type='password']#password2").val() ) {
            $("input[type='password']#password2").addClass("error");
        } else {
            $("input[type='password']#password2").removeClass("error");
        }
    });
    $("input[type='password']#new_password1, input[type='password']#new_password2").keyup(function(e) {
        if ( $("input[type='password']#new_password1").val() !== $("input[type='password']#new_password2").val() ) {
            $("input[type='password']#new_password2").addClass("error");
        } else {
            $("input[type='password']#new_password2").removeClass("error");
        }
    });
});

// Prevent tabbing through yearly archive post previews
$(document).ready(function() {
	$("article.post.archive *").not(".archive_post_link").attr("tabindex", "-1")
});

<!-- --></script>
