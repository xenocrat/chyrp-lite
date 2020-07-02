<?php
    /**
     * File: main.js.php
     * JavaScript for core functionality and extensions.
     */
    if (!defined('CHYRP_VERSION'))
        exit;
?>
'use strict';

$(function() {
    Post.init();
    Page.init();
});
var Route = {
    action: '<?php echo $route->action; ?>'
}
var Visitor = {
    token: '<?php echo authenticate(); ?>'
}
var Site = {
    url: '<?php echo addslashes($config->url); ?>',
    chyrp_url: '<?php echo addslashes($config->chyrp_url); ?>'
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

        $.post("<?php echo url('/', 'AjaxController'); ?>", {
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
        message = (typeof message === "string") ?
            message :
            '<?php echo __("Oops! Something went wrong on this web page."); ?>' ;

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

        $.post("<?php echo url('/', 'AjaxController'); ?>", {
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
        message = (typeof message === "string") ?
            message :
            '<?php echo __("Oops! Something went wrong on this web page."); ?>' ;

        Page.failed = true;
        alert(message);
        $(".ajax_loading").loader(true);
    }
}
<?php $trigger->call("javascript"); ?>
