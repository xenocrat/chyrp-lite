<?php
    /**
     * File: javascript
     * JavaScript for core functionality and extensions.
     */

    define('JAVASCRIPT', true);
    require_once "common.php";
?>
$(function() {
    if (Site.ajax) {
        Post.init();
        Page.init();
    }
});
var Route = {
    action: '<?php if (isset($_GET['action'])) echo addslashes($_GET['action']); ?>'
}
var Visitor = {
    token: '<?php if (same_origin()) echo authenticate(); ?>'
}
var Site = {
    url: '<?php echo addslashes($config->url); ?>',
    chyrp_url: '<?php echo addslashes($config->chyrp_url); ?>',
    ajax: <?php echo($config->enable_ajax ? "true" : "false"); ?> 
}
var Post = {
    failed: false,
    init: function() {
        $(".post").last().parent().on("click", ".post_delete_link:not(.no_ajax)", function(e) {
            if (!Post.failed) {
                e.preventDefault();

                if (confirm('<?php echo __("Are you sure you want to delete this post?"); ?>')) {
                    var id = $(this).attr("id");
                    var post_id = (!!id) ? id.replace(/^post_delete_/, "") : "0" ;
                    Post.destroy(post_id);
                }
            }
        });
    },
    destroy: function(id) {
        var thisPost = $("#post_" + id).loader();

        if (Visitor.token == "") {
            Post.panic('<?php echo __("The post cannot be deleted because your web browser did not send proper credentials."); ?>');
            return;
        }

        $.post(Site.chyrp_url + "/ajax/", {
            action: "destroy_post",
            id: id,
            hash: Visitor.token
        }, function(response) {
            thisPost.loader(true).fadeOut("fast", function() {
                $(this).remove();

                if (!$("article.post").length)
                    window.location.href = Site.url;
            });
        }, "json").fail(Post.panic);
    },
    panic: function(message) {
        message = (typeof message === "string") ? message : '<?php echo __("Oops! Something went wrong on this web page."); ?>' ;
        Post.failed = true;
        alert(message);
        $(".ajax_loading").loader(true);
    }
}
var Page = {
    failed: false,
    init: function() {
        $(".page_delete_link:not(.no_ajax)").on("click", function(e) {
            if (!Page.failed) {
                e.preventDefault();

                if (confirm('<?php echo __("Are you sure you want to delete this page and its child pages?"); ?>')) {
                    var id = $(this).attr("id");
                    var page_id = (!!id) ? id.replace(/^page_delete_/, "") : "0" ;
                    Page.destroy(page_id);
                }
            }
        });
    },
    destroy: function(id) {
        var thisPage = $("#page_" + id).loader();

        if (Visitor.token == "") {
            Page.panic('<?php echo __("The page cannot be deleted because your web browser did not send proper credentials."); ?>');
            return;
        }

        $.post(Site.chyrp_url + "/ajax/", {
            action: "destroy_page",
            id: id,
            hash: Visitor.token
        }, function(response) {
            thisPage.loader(true).fadeOut("fast", function() {
                $(this).remove();
                window.location.href = Site.url;
            });
        }, "json").fail(Page.panic);
    },
    panic: function(message) {
        message = (typeof message === "string") ? message : '<?php echo __("Oops! Something went wrong on this web page."); ?>' ;
        Page.failed = true;
        alert(message);
        $(".ajax_loading").loader(true);
    }
}
<?php $trigger->call("javascript"); ?>
