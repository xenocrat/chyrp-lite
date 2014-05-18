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
                        likes.log("unsuccessful request, response from server:"+ response)
                    }
                },
                error:function (xhr, ajaxOptions, thrownError) {
                    likes.log('error in AJAX request.')
                    likes.log('xhrObj:'+xhr)
                    likes.log('thrownError:'+thrownError)
                    likes.log('ajaxOptions:'+ajaxOptions)
                },
                complete:function() {
                    this.didPrevFinish = true
                },
                dataType: "json",
                cache: false
            })
        }
        likes.like = function(post_id) {
            //likes.log("like click for post-"+post_id)
            $("#likes_post-"+post_id+" a.like").fadeTo(500,.2)
            this.makeCall(post_id,function(response) {
                var postDom = $("#likes_post-"+post_id)
                postDom.children("span.text").html(response.likeText)
                var thumbImg = postDom.children("a.like").children("img")
                postDom.children("a.like").attr("title","").removeAttr("href").text("").addClass("liked").removeClass("like")
                thumbImg.appendTo(postDom.children("a.liked").eq(0))
                postDom.children("a.liked").fadeTo("500",.80)
                postDom.find(".like").hide("fast")
                //postDom.children("a.unlike").show("fast")
            }, false);
        }
        likes.unlike = function(post_id) {
            //likes.log("unlike click for post-"+post_id)
            $("#likes_post-"+post_id+" a.liked").fadeTo(500,.2)
            this.makeCall(post_id,function(response) {
                var postDom = $("#likes_post-"+post_id)
                postDom.children("span.text").html(response.likeText)
                var thumbImg = postDom.children("a.liked").children("img")
                postDom.children("a.liked").attr("href","javascript:likes.like("+post_id+")").text("").addClass("like").removeClass("liked").fadeTo("500",1)
                thumbImg.appendTo(postDom.children("a.like").eq(0))
                postDom.children("a.liked").hide("fast")
                postDom.find(".like").show("fast")
            }, true)
        }
        likes.log = function(obj){
            if(typeof console != "undefined")console.log(obj);
        }
<?php Trigger::current()->call("likes_javascript"); ?>
<!-- --></script>
