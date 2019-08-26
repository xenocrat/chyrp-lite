// Mobile navigation menu.
$(document).ready(function() {
    $("#mobile_toggle_link").on("click", function(e) {
        e.preventDefault();

        if ($("ul.tail_nav.mobile_nav").hasClass("on")) {
            $("ul.tail_nav.mobile_nav").removeClass("on");
        } else {
            $("ul.tail_nav.mobile_nav").addClass("on");
        }
    });

    $("ul.tail_nav").addClass("mobile_nav");
    $("body").addClass("mobile_nav");

    // Make the menu items keyboard accessible.
    $("ul.tail_nav.mobile_nav").on("focus", "a:not(#mobile_toggle_link)", function() {
        $("ul.tail_nav.mobile_nav").addClass("on");
    });
});
