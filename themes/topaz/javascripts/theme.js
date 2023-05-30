'use strict';

// Mobile navigation menu.
$(document).ready(function() {
    $("#mobile_toggle_link").on("click", function(e) {
        e.preventDefault();

        if ($("ul#nav.js_nav").hasClass("on")) {
            $("ul#nav.js_nav").removeClass("on");
        } else {
            $("ul#nav.js_nav").addClass("on");
        }
    });

    $("ul#nav").addClass("js_nav");
    $("body").addClass("js_nav");

    // Make the menu items keyboard accessible.
    $("ul#nav.js_nav").on("focus", "a:not(#mobile_toggle_link)", function() {
        $("ul#nav.js_nav").addClass("on");
    });
});
