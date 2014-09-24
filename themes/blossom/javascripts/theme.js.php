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

// Password strength scoring
function scorePassword(password) {
    var score = 0;
    if (!password)
        return score;

    // award every unique letter until 5 repetitions
    var letters = new Object();
    for (var i=0; i<password.length; i++) {
        letters[password[i]] = (letters[password[i]] || 0) + 1;
        score += 5.0 / letters[password[i]];
    }

    // bonus points for mixing it up
    var variations = {
        digits: /\d/.test(password),
        lower: /[a-z]/.test(password),
        upper: /[A-Z]/.test(password),
        nonWords: /\W/.test(password)
    }

    variationCount = 0;
    for (var check in variations) {
        variationCount += (variations[check] == true) ? 1 : 0;
    }
    score += (variationCount - 1) * 10;

    return parseInt(score);
}

// Password helpers
$(document).ready(function() {
    $("input[type='password']#password1, input[type='password']#new_password1").keyup(function(e) {
        var score = scorePassword($(this).val());
        $(this).css({
            "background": "url(<?php echo ( THEME_URL."/images/score.png"); ?>) #fff no-repeat top left",
            "background-size": (score + "% 100%")
        });
    });
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

// Cheeseburger mobile menu
$(function(){
    $('<li>', {
            "id": "mobile_toggle",
            "role": "button",
        }).append($("<a>", {
            "id": "cheeseburger-link",
            "href": "#",
            "aria-label": "<?php echo __("Menu", "theme"); ?>"
        }).on("click focus", function() {
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

<!-- --></script>
