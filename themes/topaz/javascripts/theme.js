'use strict';

// Mobile navigation menu.
$(document).ready(function() {
    $("#mobile_toggle_link").on("click", function(e) {
        e.preventDefault();

        if ($("ul#nav.mobile_nav").hasClass("on")) {
            $("ul#nav.mobile_nav").removeClass("on");
        } else {
            $("ul#nav.mobile_nav").addClass("on");
        }
    });

    $("ul#nav").addClass("mobile_nav");
    $("body").addClass("mobile_nav");

    // Make the menu items keyboard accessible.
    $("ul#nav.mobile_nav").on("focus", "a:not(#mobile_toggle_link)", function() {
        $("ul#nav.mobile_nav").addClass("on");
    });
});
