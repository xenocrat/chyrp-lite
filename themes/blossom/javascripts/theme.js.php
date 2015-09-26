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

// Cheeseburger mobile menu
$(document).ready(function() {
    $('<li>', {
            "id": "mobile_toggle",
            "role": "button",
        }).append($("<a>", {
            "id": "cheeseburger-link",
            "href": "#",
            "aria-label": "<?php echo __("Menu", "theme"); ?>"
        }).on("click", function() {
            if ( $(".mobile_nav").hasClass("on") ) {
                $(".mobile_nav").removeClass("on");
            } else {
                $(".mobile_nav").addClass("on");
            }
            return false;
        }).append($("<img>", {
            "id": "cheeseburger-image",
            "src": "<?php echo ( THEME_URL."/images/cheeseburger.svg"); ?>",
            "alt": "<?php echo __("Menu", "theme"); ?>"
        }).css({
            "cursor": "pointer"
        }))).appendTo("ul.tail_nav").parent().addClass("mobile_nav").parents("body").css({
            "margin-bottom": "3em"
        });

        // Make the menu items keyboard accessible
        $(".mobile_nav").children().on("focus", "a:not(#cheeseburger-link)", function() {
            $(".mobile_nav").addClass("on");
        });
});

// Prevent tabbing through yearly archive post previews
$(document).ready(function() {
    $("article.post.archive *").not(".archive_post_link").attr("tabindex", "-1")
});

<!-- --></script>
