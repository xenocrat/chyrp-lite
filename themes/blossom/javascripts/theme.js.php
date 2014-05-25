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

// Cheeseburger mobile menu
$(function(){
    $('<li>', {
            "id": "mobile_toggle",
            "role": "button",
            "aria-label": "Menu",
        }).css({
            "cursor": "pointer"
        }).click( function() {
            if ( $(this).parent().hasClass("on") ) {
                $(this).parent().removeClass("on");
            } else {
                $(this).parent().addClass("on");
            }
            return false;
        }).append($("<img>", {
            "id": "cheeseburger",
            "src": "<?php echo ( THEME_URL."/images/cheeseburger.svg") ?>",
            "alt": "Menu"
        })).appendTo("ul.tail_nav").parent().addClass("mobile_nav").parents("body").css({
            "margin-bottom": "3em"
        });
})

<!-- --></script>
