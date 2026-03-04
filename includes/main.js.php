<?php
    /**
     * File: main.js.php
     * JavaScript for core functionality and extensions.
     */
    if (!defined('CHYRP_ENVIRONMENT'))
        exit;
?>
'use strict';

$(function() {
    Post.init();
    Page.init();
});
var Route = {
    action: document.currentScript.getAttribute('data-route.action'),
    request: document.currentScript.getAttribute('data-route.request')
}
var Visitor = {
    id: parseInt(document.currentScript.getAttribute('data-visitor.id')),
    token: document.currentScript.getAttribute('data-visitor.token')
}
var Site = {
    url: document.currentScript.getAttribute('data-site.url'),
    chyrp_url: document.currentScript.getAttribute('data-site.chyrp_url'),
    ajax_url: document.currentScript.getAttribute('data-site.ajax_url')
}
var Oops = {
    message: '<?php esce(__("Oops! Something went wrong on this web page.")); ?>',
    count: 0
}
var Post = {
    failed: false,
    init: function(
    ) {
        $(".post").last().parent().on(
            "click",
            ".post_delete_link:not(.no_ajax)",
            function(e) {
                var m = '<?php esce(__("Are you sure you want to delete this post?")); ?>';

                if (!Post.failed) {
                    e.preventDefault();

                    if (confirm(m)) {
                        var id = $(this).attr("id");
                        var post_id = (!!id) ? id.replace(/^post_delete_/, "") : "0" ;
                        Post.destroy(post_id);
                    }
                }
            }
        );
    },
    destroy: function(
        id
    ) {
        var thisPost = $("#post_" + id).loader();

        $.post(
            Site.ajax_url,
            {
                action: "destroy_post",
                id: id,
                hash: Visitor.token
            },
            function(response) {
                thisPost.loader(true).fadeOut(
                    "fast",
                    function() {
                        var prev_post = $(this).prev("article.post");
                        $(this).remove();

                        if (!$("article.post").length)
                            window.location.href = Site.url;

                        if (prev_post.length)
                            prev_post.focus();
                    }
                );
            },
            "json"
        ).fail(Post.panic);
    },
    panic: function(
        message
    ) {
        message = (typeof message === "string") ?
            message :
            Oops.message ;

        Post.failed = true;
        Oops.count++;
        alert(message);
        $(".ajax_loading").loader(true);
    }
}
var Page = {
    failed: false,
    init: function(
    ) {
        $(".page_delete_link:not(.no_ajax)").on(
            "click",
            function(e) {
                var m = '<?php esce(__("Are you sure you want to delete this page and its child pages?"));?>';

                if (!Page.failed) {
                    e.preventDefault();

                    if (confirm(m)) {
                        var id = $(this).attr("id");
                        var page_id = (!!id) ? id.replace(/^page_delete_/, "") : "0" ;
                        Page.destroy(page_id);
                    }
                }
            }
        );
    },
    destroy: function(
        id
    ) {
        var thisPage = $("#page_" + id).loader();

        $.post(
            Site.ajax_url,
            {
                action: "destroy_page",
                id: id,
                hash: Visitor.token
            },
            function(response) {
                thisPage.loader(true).fadeOut(
                    "fast",
                    function() {
                        $(this).remove();
                        window.location.href = Site.url;
                    }
                );
            },
            "json"
        ).fail(Page.panic);
    },
    panic: function(
        message
    ) {
        message = (typeof message === "string") ?
            message :
            Oops.message ;

        Page.failed = true;
        Oops.count++;
        alert(message);
        $(".ajax_loading").loader(true);
    }
}
<?php $trigger->call("javascript"); ?>
