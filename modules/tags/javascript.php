<?php
    if (!defined('CHYRP_VERSION'))
        exit;
?>
var ChyrpTags = {
    init: function(
    ) {
        $("form input[name='tags']").on(
            "keyup",
            ChyrpTags.scan
        ).trigger("keyup");
        $("form span.tags_select a").on(
            "click",
            ChyrpTags.toggle
        );
    },
    scan: function(
        e
    ) {
        $(e.target).siblings("span.tags_select").children("a.tag").each(
            function() {
                var name = $(this).html();
                var regexp = new RegExp("(, *|^)" + escapeRegExp(name) + "(, *|$)", "g");

                if ($(e.target).val().match(regexp))
                    $(this).addClass("tag_added");
                else
                    $(this).removeClass("tag_added");
            }
        );
    },
    toggle: function(
        e
    ) {
        e.preventDefault();
        var name = $(e.target).html();
        var tags = $(e.target).parent().siblings("input[name='tags']");
        var regexp = new RegExp("(, *|^)" + escapeRegExp(name) + "(, *|$)", "g");

        if (regexp.test(tags.val())) {
            tags.val(
                tags.val().replace(
                    regexp,
                    function(match, before, after) {
                        return (before && after) ? ", " : "" ;
                    }
                )
            );
            $(e.target).removeClass("tag_added");
        } else {
            tags.val(
                tags.val().replace(/([^,])(, *)?$/, "$1, ") + name
            );
            $(e.target).addClass("tag_added");
        }
    }
};
$(document).ready(ChyrpTags.init);
