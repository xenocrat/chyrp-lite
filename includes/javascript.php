<?php
    define('JAVASCRIPT', true);
    require_once "common.php";
    error_reporting(0);
    header("Content-Type: application/x-javascript");
?>
<!-- --><script>
$(function(){
    // Scan AJAX responses for errors.
    $(document).ajaxComplete(function(event, request){
        var response = request ? request.responseText : null;
        if (isError(response))
            alert(response.replace(/(HEY_JAVASCRIPT_THIS_IS_AN_ERROR_JUST_SO_YOU_KNOW|<([^>]+)>\n?)/gm, ""));
    })<?php echo "\n\n\n\n"; # Balance out the line numbers in this script and in the output to help debugging. ?>

    $(".toggle_admin").click(function(){
        if (!$("#admin_bar:visible, #controls:visible").size())
            Cookie.destroy("hide_admin");
        else
            Cookie.set("hide_admin", "true", 30);

        $("#admin_bar, #controls").slideToggle();
        return false;
    })

<?php if (!isset($config->enable_ajax) or $config->enable_ajax): ?> Post.prepare_links()<?php endif; ?>
})

var Route = {
    action: "<?php echo $_GET['action']; ?>"
};

var site_url = "<?php echo $config->chyrp_url; ?>";

var Post = {
    id: 0,
    edit: function(id) {
        Post.id = id;
        $("#post_"+id).loader();
        $.post("<?php echo $config->chyrp_url; ?>/includes/ajax.php", { action: "edit_post", id: id }, function(data) {
            $("#post_"+id).loader(true).fadeOut("fast", function(){
                $(this).replaceWith(data);
                $("#post_edit_form_"+id).css("opacity", 0).animate({ opacity: 1 }, function(){
<?php $trigger->call("ajax_post_edit_form_javascript"); ?>
                    $("#more_options_link_"+id).click(function(){
                        if ($("#more_options_"+id).css("display") == "none") {
                            $(this).empty().append("<?php echo __("&uarr; Fewer Options"); ?>");
                            $("#more_options_"+id).slideDown("slow");
                        } else {
                            $(this).empty().append("<?php echo __("More Options &darr;"); ?>");
                            $("#more_options_"+id).slideUp("slow");
                        }
                        return false;
                    });
                    $("#post_edit_form_"+id).ajaxForm({ beforeSubmit: function(){
                        $("#post_edit_form_"+id).loader();
                    }, success: Post.updated })
                    $("#post_cancel_edit_"+id).click(function(){
                        $("#post_edit_form_"+id).loader();
                        $.post("<?php echo $config->chyrp_url; ?>/includes/ajax.php", {
                            action: "view_post",
                            context: "all",
                            id: id,
                            reason: "cancelled"
                        }, function(data) {
                            $("#post_edit_form_"+id).loader(true).fadeOut("fast", function(){
                                $(this).replaceWith(data)
                                $(this).hide().fadeIn("fast")
                            });
                        }, "html");
                        return false
                    });
                });
            });
        }, "html");
    },
    updated: function(response){
        id = Post.id
        if (isError(response))
            return $("#post_edit_form_"+id).loader(true);

        if (Route.action != "drafts" && Route.action != "view" && $("#post_edit_form_"+id+" select#status").val() == "draft") {
            $("#post_edit_form_"+id).loader(true).fadeOut("fast", function(){
                alert("<?php echo __("Post has been saved as a draft."); ?>");
            })
        } else if (Route.action == "drafts" && $("#post_edit_form_"+id+" select#status").val() != "draft") {
            $("#post_edit_form_"+id).loader(true).fadeOut("fast", function(){
                alert("<?php echo __("Post has been published."); ?>");
            })
        } else {
            $.post("<?php echo $config->chyrp_url; ?>/includes/ajax.php", {
                action: "view_post",
                context: "all",
                id: id,
                reason: "edited"
            }, function(data) {
                $("#post_edit_form_"+id).loader(true).fadeOut("fast", function(){
                    $(this).replaceWith(data)
                    $("#post_"+id).hide().fadeIn("fast")
                });
            }, "html");
        }
    },
    destroy: function(id) {
        $("#post_"+id).loader()
        $.post("<?php echo $config->chyrp_url; ?>/includes/ajax.php", { action: "delete_post", id: id }, function(response) {
            $("#post_"+id).loader(true);
            if (isError(response)) return;

            $("#post_"+id).fadeOut("fast", function(){
                $(this).remove();

                if (Route.action == "view")
                    window.location = "<?php echo $config->url; ?>";
            });
        }, "html");
    },
    prepare_links: function(id) {
        $(".post_edit_link:not(.no_ajax)").live("click", function(){
            var id = $(this).attr("id").replace(/post_edit_/, "")
            Post.edit(id)
            return false
        });

        $(".post_delete_link").live("click", function(){
            if (!confirm("<?php echo __("Are you sure you want to delete this post?\\n\\nIt cannot be restored if you do this. If you wish to hide it, save it as a draft."); ?>")) return false
            var id = $(this).attr("id").replace(/post_delete_/, "")
            Post.destroy(id)
            return false
        });
    }
}

<?php echo "\n"; $trigger->call("javascript"); ?>
<!-- --></script>
