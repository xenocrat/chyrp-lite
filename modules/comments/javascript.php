<?php
    if (!defined('CHYRP_VERSION'))
        exit;
?>
var ChyrpComment = {
    editing: 0,
    notice: 0,
    interval: null,
    failed: false,
    reload: <?php echo($config->module_comments["enable_reload_comments"] ? "true" : "false" ); ?>,
    delay: Math.abs(<?php echo($config->module_comments["auto_reload_comments"] * 1000); ?>),
    per_page: <?php echo $config->module_comments["comments_per_page"]; ?>,
    init: function() {
        if (ChyrpComment.reload && ChyrpComment.delay > 0)
            ChyrpComment.interval = setInterval(ChyrpComment.fetch, ChyrpComment.delay);

        $("#add_comment:not(.no_ajax)").on("submit", function(e) {
            if (!ChyrpComment.failed && !!window.FormData) {
                e.preventDefault();
                $("#comments .comment_form").loader();

                // Submit the form.
                $.ajax({
                    error: ChyrpComment.panic,
                    type: "POST",
                    url: "<?php echo url('/', 'AjaxController'); ?>",
                    data: new FormData(this),
                    processData: false,
                    contentType: false,
                    dataType: "json"
                }).done(function(response) {
                    $("#comments .comment_form").loader(true);
                    alert(response.text);

                    // Reload the page to view the newly created comment.
                    if (response.data !== false)
                        window.location.reload(true);
                });
            }
        });
        $("#comments").on("click", ".comment_edit_link:not(.no_ajax)", function(e) {
            if (!ChyrpComment.failed) {
                e.preventDefault();
                var id = $(this).attr("id").replace(/comment_edit_/, "");
                ChyrpComment.edit(id);
            }
        });
        $("#comments").on("click", ".comment_delete_link:not(.no_ajax)", function(e) {
            if (!ChyrpComment.failed) {
                e.preventDefault();
                ChyrpComment.notice++;

                if (confirm('<?php echo __("Are you sure you want to permanently delete this comment?", "comments"); ?>')) {
                    var id = $(this).attr("id").replace(/comment_delete_/, "");
                    ChyrpComment.destroy(id);
                }

                ChyrpComment.notice--;
            }
        });
    },
    fetch: function() {
        if (ChyrpComment.failed ||
            $("#comments").attr("data-post_id") == undefined ||
            $("#comments").attr("data-timestamp") == undefined)
                return;

        var comments = $("#comments");
        var id = comments.attr("data-post_id");
        var ts = comments.attr("data-timestamp");

        if (ChyrpComment.editing == 0 && ChyrpComment.notice == 0 && !ChyrpComment.failed &&
            $("#comments .comment").length < ChyrpComment.per_page) {
                $.ajax({
                    error: ChyrpComment.panic,
                    type: "POST",
                    dataType: "json",
                    url: "<?php echo url('/', 'AjaxController'); ?>",
                    data: {
                        action: "reload_comments",
                        post_id: id,
                        last_comment: ts
                    }
                }).done(function(response) {
                    if (response.data.comment_ids.length > 0) {
                        $("#comments").attr("data-timestamp", response.data.last_comment);
                        $.each(response.data.comment_ids, function(i, id) {
                            $.post("<?php echo url('/', 'AjaxController'); ?>", {
                                action: "show_comment",
                                comment_id: id
                            }, function(data){
                                $(data).insertBefore("#comment_shim").hide().fadeIn("slow");
                            }, "html").fail(ChyrpComment.panic);
                        });
                    }
                });
        }
    },
    edit: function(id) {
        ChyrpComment.editing++;

        var thisItem = $("#comment_" + id).loader();

        $.post("<?php echo url('/', 'AjaxController'); ?>", {
            action: "edit_comment",
            comment_id: id,
            hash: Visitor.token
        }, function(data) {
            thisItem.fadeOut("fast", function() {
                $(this).loader(true);
                $(this).empty().append(data).fadeIn("fast", function() {
                    var thisForm = $("#comment_edit_" + id);

                    thisForm.on("submit", function(e) {
                        if (!ChyrpComment.failed && !!window.FormData) {
                            e.preventDefault();
                            thisItem.loader();

                            // Submit the form.
                            $.ajax({
                                error: ChyrpComment.panic,
                                type: "POST",
                                url: "<?php echo url('/', 'AjaxController'); ?>",
                                data: new FormData(thisForm[0]),
                                processData: false,
                                contentType: false,
                                dataType: "json"
                            }).done(function(response) {
                                // Validation failed if data value is false.
                                if (response.data === false) {
                                    $(thisItem).loader(true);
                                    alert(response.text);
                                    return;
                                }

                                ChyrpComment.editing--;

                                // Load the updated post in place of the edit form.
                                $.post("<?php echo url('/', 'AjaxController'); ?>", {
                                    action: "show_comment",
                                    comment_id: id
                                }, function(data) {
                                    thisItem.fadeOut("fast", function() {
                                        $(this).replaceWith(data).fadeIn("fast");
                                    });
                                }, "html").fail(ChyrpComment.panic);
                            });
                        }
                    });
                    $("#comment_cancel_edit_" + id).click(function(e) {
                        e.preventDefault();

                        if (!ChyrpComment.failed) {
                            thisItem.loader();
                            $.post("<?php echo url('/', 'AjaxController'); ?>", {
                                action: "show_comment",
                                comment_id: id
                            }, function(data){
                                thisItem.fadeOut("fast", function() {
                                    $(this).replaceWith(data).fadeIn("fast");
                                });
                            });
                            ChyrpComment.editing--;
                        }
                    });
                })
            });
        }, "html").fail(ChyrpComment.panic);
    },
    destroy: function(id) {
        var thisItem = $("#comment_" + id).loader();

        $.post("<?php echo url('/', 'AjaxController'); ?>", {
            action: "destroy_comment",
            id: id,
            hash: Visitor.token
        }, function(response){
            thisItem.fadeOut("fast", function() {
                $(this).remove();
            });
        }, "json").fail(ChyrpComment.panic);
    },
    panic: function(message) {
        message = (typeof message === "string") ?
            message :
            '<?php echo __("Oops! Something went wrong on this web page."); ?>' ;

        ChyrpComment.failed = true;
        alert(message);
        $(".ajax_loading").loader(true);
        $("#comments form input[name='ajax']").remove();
    }
};
$(document).ready(ChyrpComment.init);
