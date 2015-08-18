        var ChyrpLikes = {
            action: "like",
            didPrevFinish: true,
            init: function() {
                $("div.likes a.likes").click(function() {
                    ChyrpLikes.toggle($(this).attr("data-post_id"));
                    return false;
                });
                ChyrpLikes.watch();
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
                if (!this.didPrevFinish) return false;
                if (isUnlike == true) this.action = "unlike"; else this.action = "like";
                params = {};
                params["action"] = this.action;
                params["post_id"] = post_id;
                jQuery.ajax({
                    type: "POST",
                    url: "<?php echo Config::current()->chyrp_url; ?>/includes/ajax.php",
                    data: params,
                    beforeSend: function() {
                        this.didPrevFinish = false;	
                    },
                    success:function(response) {
                        if(response.success == true) {
                            callback(response);
                        }
                        else {
                            ChyrpLikes.log("unsuccessful request, response from server: " + response.error_text);
                        }
                    },
                    error:function (xhr, ajaxOptions, thrownError) {
                        ChyrpLikes.log('error in AJAX request.');
                        ChyrpLikes.log('xhrObj: ' + xhr);
                        ChyrpLikes.log('thrownError: ' + thrownError);
                        ChyrpLikes.log('ajaxOptions: ' + ajaxOptions);
                    },
                    complete:function() {
                        this.didPrevFinish = true;
                    },
                    dataType: "json",
                    cache: false
                })
            },
            toggle: function(post_id) {
                if ( $("#likes_post-"+post_id+" a.liked").length ) {
                    this.unlike(post_id);
                } else {
                    this.like(post_id);
                }
            },
            like: function(post_id) {
                this.makeCall(post_id,function(response) {
                    var postDom = $("#likes_post-"+post_id);
                    postDom.children("span.like_text").html(response.likeText);
                    postDom.children("a.like").removeClass("like").addClass("liked");
                }, false);
            },
            unlike: function(post_id) {
                this.makeCall(post_id,function(response) {
                    var postDom = $("#likes_post-"+post_id);
                    postDom.children("span.like_text").html(response.likeText);
                    postDom.children("a.liked").removeClass("liked").addClass("like");
                }, true);
            },
            log: function(obj){
                if(typeof console != "undefined")console.log(obj);
            }
        };
        $(document).ready(ChyrpLikes.init);
