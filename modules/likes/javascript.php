        var ChyrpLikes = {
            action: "like",
            failed: false,
            didPrevFinish: true,
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
                // Watch for DOM additions on blog pages
                if ( !!window.MutationObserver && $(".post").length ) {
                    var target = $(".post").last().parent()[0];
                    var observer = new MutationObserver(function(mutations) {
                        mutations.forEach(function(mutation) {
                            for (var i = 0; i < mutation.addedNodes.length; ++i) {
                                var item = mutation.addedNodes[i];
                                $(item).find("div.likes a.likes").click(function() {
                                    ChyrpLikes.toggle($(this).attr("data-post_id"));
                                    return false;
                                });
                            }
                        });
                    });
                    var config = { childList: true, subtree: true };
                    observer.observe(target, config);
                }
            },
            makeCall: function(post_id, callback, isUnlike) {
                if (!ChyrpLikes.didPrevFinish)
                    return false;

                if (isUnlike == true)
                    ChyrpLikes.action = "unlike";
                else
                    ChyrpLikes.action = "like";

                params = {};
                params["action"] = ChyrpLikes.action;
                params["post_id"] = post_id;
                $.ajax({
                    type: "POST",
                    url: Site.url + "/includes/ajax.php",
                    data: params,
                    beforeSend: function() {
                        ChyrpLikes.didPrevFinish = false;	
                    },
                    success: function(response) {
                        if(response.success == true)
                            callback(response);
                        else
                            ChyrpLikes.panic();
                    },
                    complete: function(response) {
                        ChyrpLikes.didPrevFinish = true;
                    },
                    dataType: "json",
                    cache: false,
                    error: ChyrpLikes.panic
                })
            },
            toggle: function(post_id) {
                if ($("#likes_post-"+post_id+" a.liked").length)
                    ChyrpLikes.unlike(post_id);
                else
                    ChyrpLikes.like(post_id);
            },
            like: function(post_id) {
                ChyrpLikes.makeCall(post_id,function(response) {
                    var postDom = $("#likes_post-"+post_id);
                    postDom.children("span.like_text").html(response.likeText);
                    postDom.children("a.like").removeClass("like").addClass("liked");
                }, false);
            },
            unlike: function(post_id) {
                ChyrpLikes.makeCall(post_id,function(response) {
                    var postDom = $("#likes_post-"+post_id);
                    postDom.children("span.like_text").html(response.likeText);
                    postDom.children("a.liked").removeClass("liked").addClass("like");
                }, true);
            },
            panic: function() {
                ChyrpLikes.failed = true;
                alert("<?php echo __("Oops! Something went wrong on this web page."); ?>");
            }
        };
        $(document).ready(ChyrpLikes.init);
