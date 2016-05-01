var ChyrpLikes = {
    failed: false,
    busy: false,
    init: function() {
        if (Site.ajax) {
            $("div.likes a.likes").click(function(e) {
                if (!ChyrpLikes.failed) {
                    e.preventDefault();
                    ChyrpLikes.toggle($(this).attr("data-post_id"));
                }
            });
            ChyrpLikes.watch();
        }
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
        if (ChyrpLikes.busy)
            return false;

        $.ajax({
            type: "POST",
            url: Site.chyrp_url + "/includes/ajax.php",
            data: {
                "action": (isUnlike) ? "unlike" : "like",
                "post_id": post_id
            },
            beforeSend: function() {
                ChyrpLikes.busy = true;	
            },
            success: function(response) {
                if (isError(response)) {
                    ChyrpLikes.panic();
                    return;
                }

                if (response != "")
                    callback(response);
            },
            complete: function(response) {
                ChyrpLikes.busy = false;
            },
            dataType: "html",
            cache: false,
            error: ChyrpLikes.panic
        });
    },
    toggle: function(post_id) {
        if ($("#likes_" + post_id + " a.liked").length)
            ChyrpLikes.send(post_id, function(response) {
                var div = $("#likes_" + post_id);
                div.children("span.like_text").html(response);
                div.children("a.liked").removeClass("liked").addClass("like");
            }, true);
        else
            ChyrpLikes.send(post_id, function(response) {
                var div = $("#likes_" + post_id);
                div.children("span.like_text").html(response);
                div.children("a.like").removeClass("like").addClass("liked");
            }, false);
    },
    panic: function() {
        ChyrpLikes.failed = true;
        alert('<?php echo __("Oops! Something went wrong on this web page."); ?>');
    }
};
$(document).ready(ChyrpLikes.init);
