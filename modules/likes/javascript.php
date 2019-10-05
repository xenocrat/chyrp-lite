<?php
    if (!defined('CHYRP_VERSION'))
        exit;
?>
var ChyrpLikes = {
    failed: false,
    busy: false,
    init: function() {
        $("div.likes a.likes").click(function(e) {
            if (!ChyrpLikes.failed) {
                e.preventDefault();
                ChyrpLikes.toggle($(this).attr("data-post_id"));
            }
        });
        ChyrpLikes.watch();
    },
    watch: function() {
        // Watch for DOM additions on blog pages.
        if (!!window.MutationObserver && $(".post").length) {
            var target = $(".post").last().parent()[0];
            var observer = new MutationObserver(function(mutations) {
                mutations.forEach(function(mutation) {
                    for (var i = 0; i < mutation.addedNodes.length; ++i) {
                        var item = mutation.addedNodes[i];
                        $(item).find("div.likes a.likes").click(function(e) {
                            e.preventDefault();
                            ChyrpLikes.toggle($(this).attr("data-post_id"));
                        });
                    }
                });
            });
            var config = { childList: true, subtree: true };
            observer.observe(target, config);
        }
    },
    send: function(post_id, callback, isUnlike) {
        if (!ChyrpLikes.busy && !ChyrpLikes.failed) {
            $.ajax({
                type: "POST",
                url: "<?php echo url('/', 'AjaxController'); ?>",
                data: {
                    "action": (isUnlike) ? "unlike" : "like",
                    "post_id": post_id
                },
                beforeSend: function() {
                    ChyrpLikes.busy = true;	
                },
                success: function(response) {
                    // Action was ignored if data value is false.
                    if (response.data === true)
                        callback(response);
                },
                complete: function() {
                    ChyrpLikes.busy = false;
                },
                dataType: "json",
                error: ChyrpLikes.panic
            });
        }
    },
    toggle: function(post_id) {
        if ($("#likes_" + post_id + " a.liked").length)
            ChyrpLikes.send(post_id, function(response) {
                var div = $("#likes_" + post_id);
                div.children("span.like_text").html(response.text);
                div.children("a.liked").removeClass("liked").addClass("like");
            }, true);
        else
            ChyrpLikes.send(post_id, function(response) {
                var div = $("#likes_" + post_id);
                div.children("span.like_text").html(response.text);
                div.children("a.like").removeClass("like").addClass("liked");
            }, false);
    },
    panic: function() {
        ChyrpLikes.failed = true;
        alert('<?php echo __("Oops! Something went wrong on this web page."); ?>');
    }
};
$(document).ready(ChyrpLikes.init);
