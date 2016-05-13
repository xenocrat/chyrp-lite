<?php
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
    action: "<?php echo fix(@$_GET['action']); ?>"
}
var Site = {
    url: '<?php echo $config->url; ?>',
    chyrp_url: '<?php echo $config->chyrp_url; ?>',
    key: '<?php if (same_origin() and logged_in()) echo token($_SERVER["REMOTE_ADDR"]); ?>',
    ajax: <?php echo($config->enable_ajax ? "true" : "false"); ?> 
}
var Post = {
    failed: false,
    init: function() {
        $(".post").last().parent().on("click", ".post_delete_link:not(.no_ajax)", function(e) {
            if (!Post.failed) {
                e.preventDefault();

                if (confirm('<?php echo __("Are you sure you want to delete this post? If you wish to hide it, save it as a draft."); ?>')) {
                    var id = $(this).attr("id").replace(/post_delete_/, "");
                    Post.destroy(id);
                }
            }
        });
    },
    destroy: function(id) {
        $("#post_" + id).loader();

        if (Site.key == "") {
            Post.panic('<?php echo __("The post cannot be deleted because your web browser did not send proper credentials."); ?>');
            return;
        }

        $.post(Site.chyrp_url + "/includes/ajax.php", {
            action: "destroy_post",
            id: id,
            hash: Site.key
        }, function(response) {
            $("#post_" + id).loader(true);

            if (isError(response)) {
                Post.panic();
                return;
            }

            $("#post_" + id).fadeOut("fast", function() {
                $(this).remove();

                if (Route.action == "view")
                    window.location = Site.url;
            });
        }, "html").fail(Post.panic);
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

                if (confirm('<?php echo __("Are you sure you want to delete this page? Child pages will also be deleted."); ?>')) {
                    var id = $(this).attr("id").replace(/page_delete_/, "");
                    Page.destroy(id);
                }
            }
        });
    },
    destroy: function(id) {
        $("#page_" + id).loader();

        if (Site.key == "") {
            Page.panic('<?php echo __("The page cannot be deleted because your web browser did not send proper credentials."); ?>');
            return;
        }

        $.post(Site.chyrp_url + "/includes/ajax.php", {
            action: "destroy_page",
            id: id,
            hash: Site.key
        }, function(response) {
            $("#page_" + id).loader(true);

            if (isError(response)) {
                Page.panic();
                return;
            }

            $("#page_" + id).fadeOut("fast", function() {
                $(this).remove();
                window.location = Site.url;
            });
        }, "html").fail(Page.panic);
    },
    panic: function(message) {
        message = (typeof message === "string") ? message : '<?php echo __("Oops! Something went wrong on this web page."); ?>' ;
        Page.failed = true;
        alert(message);
        $(".ajax_loading").loader(true);
    }
}
<?php $trigger->call("javascript"); ?>
