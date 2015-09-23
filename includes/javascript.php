<?php
    define('JAVASCRIPT', true);
    require_once "common.php";
    error_reporting(0);
    header("Content-Type: application/javascript");
?>
<!-- --><script>
        <?php echo "/* Balance out the line numbers in this script and in the output to help debugging.\n\n\n\n\n        */\n"; ?>
        $(function() {
            // Scan AJAX responses for errors.
            $(document).ajaxComplete(function(event, request){
                var response = request ? request.responseText : null;
                if (isError(response))
                    alert(response.replace(/(HEY_JAVASCRIPT_THIS_IS_AN_ERROR_JUST_SO_YOU_KNOW|<([^>]+)>\n?)/gm, ""));
            });

            if (Site.ajax)
                Post.prepare_links();
        });
        var Route = {
            action: "<?php echo $_GET['action']; ?>"
        };
        var Site = {
            url: "<?php echo $config->chyrp_url; ?>",
            key: "<?php if (logged_in() and preg_match("/^".preg_quote($config->url, "/").".*/", $_SERVER["HTTP_REFERER"])) echo token($_SERVER["REMOTE_ADDR"]); ?>",
            ajax: <?php if (!isset($config->enable_ajax) or $config->enable_ajax) echo("true"); else echo("false"); ?>
        };
        var Post = {
            id: 0,
            failed: false,
            edit: function(id) {
                Post.id = id;
                $("#post_" + id).loader();
                $.post(Site.url + "/includes/ajax.php", {
                    action: "edit_post",
                    id: id,
                    hash: Site.key
                }, function(data) {
                    $("#post_" + id).fadeOut("fast", function(){
                        $(this).loader(true);
                        $(this).replaceWith(data);
                        $(window).scrollTop($("#post_edit_form_" + id).offset().top);
                        $("#post_edit_form_" + id).css("opacity", 0).animate({ opacity: 1 }, function(){
<?php $trigger->call("ajax_post_edit_form_javascript"); ?>
                            $("#more_options_link_" + id).click(function(e){
                                e.preventDefault();

                                if ($("#more_options_" + id).css("display") == "none") {
                                    $(this).empty().append("<?php echo __("&uarr; Fewer Options"); ?>");
                                    $("#more_options_" + id).slideDown("slow");
                                } else {
                                    $(this).empty().append("<?php echo __("More Options &darr;"); ?>");
                                    $("#more_options_" + id).slideUp("slow");
                                }
                            });
                            $("#post_edit_form_" + id).on( "submit", function(e){
                                if (!Post.failed && !!window.FormData) {
                                    e.preventDefault();
                                    $(this).loader();
                                    $.ajax({
                                        type: "POST",
                                        url: $(this).attr("action"),
                                        data: new FormData(this),
                                        processData: false,
                                        contentType: false,
                                        dataType: "text",
                                        error: Post.panic
                                    }).done(Post.updated);
                                }
                            });
                            $("#post_cancel_edit_" + id).click(function(e){
                                e.preventDefault();

                                if (!Post.failed) {
                                    $("#post_edit_form_" + id).loader();
                                    $.post(Site.url + "/includes/ajax.php", {
                                        action: "view_post",
                                        context: Route.action,
                                        id: id,
                                        reason: "cancelled"
                                    }, function(data) {
                                        $("#post_edit_form_" + id).fadeOut("fast", function(){
                                            $(this).loader(true);
                                            $(this).replaceWith(data);
                                            $(this).hide().fadeIn("fast");
                                        });
                                    }, "html").fail(Post.panic);
                                }
                            });
                        });
                    });
                }, "html").fail(Post.panic);
            },
            updated: function(response){
                id = Post.id;

                if (isError(response)) {
                    Post.panic();
                    $("#post_edit_form_" + id).loader(true);
                    return;
                }

                if (Route.action != "drafts" && Route.action != "view" && $("#post_edit_form_" + id + " select#status").val() == "draft") {
                    $("#post_edit_form_" + id).fadeOut("fast", function(){
                        $(this).loader(true);
                        alert("<?php echo __("Post has been saved as a draft."); ?>");
                    })
                } else if (Route.action == "drafts" && $("#post_edit_form_" + id + " select#status").val() != "draft") {
                    $("#post_edit_form_" + id).fadeOut("fast", function(){
                        $(this).loader(true);
                        alert("<?php echo __("Post has been published."); ?>");
                    })
                } else {
                    $.post(Site.url + "/includes/ajax.php", {
                        action: "view_post",
                        context: Route.action,
                        id: id,
                        reason: "edited"
                    }, function(data) {
                        $("#post_edit_form_" + id).fadeOut("fast", function(){
                            $(this).loader(true);
                            $(this).replaceWith(data);
                            $("#post_" + id).hide().fadeIn("fast");
                        });
                    }, "html").fail(Post.panic);
                }
            },
            destroy: function(id) {
                $("#post_" + id).loader();
                $.post(Site.url + "/includes/ajax.php", {
                    action: "delete_post",
                    id: id,
                    hash: Site.key
                }, function(response) {
                    $("#post_" + id).loader(true);

                    if (isError(response)) {
                        Post.panic();
                        return;
                    }

                    $("#post_" + id).fadeOut("fast", function(){
                        $(this).remove();

                        if (Route.action == "view")
                            window.location = "<?php echo $config->url; ?>";
                    });
                }, "html").fail(Post.panic);
            },
            prepare_links: function(id) {
                $(".post").last().parent().on("click", ".post_edit_link:not(.no_ajax)", function(e){
                    if (!Post.failed) {
                        e.preventDefault();
                        var id = $(this).attr("id").replace(/post_edit_/, "");
                        Post.edit(id);
                    }
                });
                $(".post").last().parent().on("click", ".post_delete_link:not(.no_ajax)", function(e){
                    if (!Post.failed) {
                        e.preventDefault();

                        if (confirm("<?php echo __("Are you sure you want to delete this post?\\n\\nIt cannot be restored if you do this. If you wish to hide it, save it as a draft."); ?>")) {
                            var id = $(this).attr("id").replace(/post_delete_/, "");
                            Post.destroy(id);
                        }
                    }
                });
            },
            panic: function() {
                Post.failed = true;
                alert("<?php echo __("Oops! Something went wrong on this web page."); ?>");
            }
        }
<?php $trigger->call("javascript"); ?>
<!-- --></script>
