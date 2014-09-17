<?php
    define('JAVASCRIPT', true);
    require_once "../../includes/common.php";
    error_reporting(0);
    header("Content-Type: application/x-javascript");
?>
<!-- --><script>
        var likes = {};
        likes.action = "like";
        likes.didPrevFinish = true;
        likes.init = function() {
            $("div.likes a").css("display", "inline"); // Enable liking
            likes.watch();
        }
        likes.watch = function() {
            // Watch for DOM additions on blog pages
            if ( !!window.MutationObserver && $(".post").length ) {
                var target = $(".post").last().parent()[0];
                var observer = new MutationObserver(function(mutations) {
                    mutations.forEach(function(mutation) {
                        for (var i = 0; i < mutation.addedNodes.length; ++i) {
                            var item = mutation.addedNodes[i];
                            $(item).find("div.likes a").css("display", "inline");
                        }
                    });
                });
                var config = { childList: true, subtree: true };
                observer.observe(target, config);
            }
        }
        likes.makeCall = function(post_id, callback, isUnlike) {
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
                        likes.log("unsuccessful request, response from server:"+ response);
                    }
                },
                error:function (xhr, ajaxOptions, thrownError) {
                    likes.log('error in AJAX request.');
                    likes.log('xhrObj:'+xhr);
                    likes.log('thrownError:'+thrownError);
                    likes.log('ajaxOptions:'+ajaxOptions);
                },
                complete:function() {
                    this.didPrevFinish = true;
                },
                dataType: "json",
                cache: false
            })
        }
        likes.toggle = function(post_id) {
            if ( $("#likes_post-"+post_id+" a.liked").length ) {
                this.unlike(post_id);
            } else {
                this.like(post_id);
            }
        }
        likes.like = function(post_id) {
            this.makeCall(post_id,function(response) {
                var postDom = $("#likes_post-"+post_id);
                postDom.children("span.text").html(response.likeText);
                postDom.children("a.like").removeClass("like").addClass("liked");
            }, false);
        }
        likes.unlike = function(post_id) {
            this.makeCall(post_id,function(response) {
                var postDom = $("#likes_post-"+post_id);
                postDom.children("span.text").html(response.likeText);
                postDom.children("a.liked").removeClass("liked").addClass("like");
            }, true);
        }
        likes.log = function(obj){
            if(typeof console != "undefined")console.log(obj);
        }
        $(document).ready(likes.init);
<?php Trigger::current()->call("likes_javascript"); ?>
<!-- --></script>
