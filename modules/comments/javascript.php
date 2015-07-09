        var ChyrpComment = {
            editing: 0,
            notice: 0,
            interval: null,
            failed: false,
            init: function() {
                if ($("#comments").size()) {
<?php if (Config::current()->auto_reload_comments and Config::current()->enable_reload_comments): ?>
                    ChyrpComment.interval = setInterval("ChyrpComment.reload()", Math.abs(<?php echo Config::current()->auto_reload_comments * 1000; ?>));
<?php endif; ?>
                    $("#add_comment").append($(document.createElement("input")).attr({
                        type: "hidden",
                        name: "ajax",
                        value: "true",
                        id: "ajax"
                    }));
                    $("#add_comment").ajaxForm({
                        dataType: "json",
                        resetForm: true,
                        beforeSubmit: function() {
                            $("#add_comment").parent().loader();
                        },
                        success: function(json) {
                            $.post("<?php echo Config::current()->chyrp_url; ?>/includes/ajax.php", { action: "show_comment", comment_id: json.comment_id, reason: "added" }, function(data) {
                                $("#comments").attr("data-timestamp", json.comment_timestamp);
                                $(data).insertBefore("#comment_shim").hide().fadeIn("slow");
                            }, "html");
                        },
                        complete: function() {
                            $("#add_comment").parent().loader(true);
                        }
                    });
                }
<?php if (!isset(Config::current()->enable_ajax) or Config::current()->enable_ajax): ?>
                $("#comments").on("click", ".comment_edit_link", function() {
                    var id = $(this).attr("id").replace(/comment_edit_/, "");
                    ChyrpComment.edit(id);
                    return false;
                });
                $("#comments").on("click", ".comment_delete_link", function() {
                    var id = $(this).attr("id").replace(/comment_delete_/, "");
                    ChyrpComment.notice++;
                    if (!confirm('<?php echo __("Are you sure you want to permanently delete this comment?", "comments"); ?>')) {
                        ChyrpComment.notice--;
                        return false;
                    }
                    ChyrpComment.notice--;
                    ChyrpComment.destroy(id);
                    return false;
                });
<?php endif; ?>
            },
            reload: function() {
                if ($("#comments").attr("data-post") == undefined) return;
                var id = $("#comments").attr("data-post");
                if (ChyrpComment.editing == 0 && ChyrpComment.notice == 0 && ChyrpComment.failed != true && $(".comments.paginated").children().size() < <?php echo Config::current()->comments_per_page; ?>) {
                    $.ajax({
                        type: "post",
                        dataType: "json",
                        url: "<?php echo Config::current()->chyrp_url; ?>/includes/ajax.php",
                        data: "action=reload_comments&post_id="+id+"&last_comment="+$("#comments").attr("data-timestamp"),
                        success: function(json) {
                            if ( json != null ) {
                                $("#comments").attr("data-timestamp", json.last_comment);
                                $.each(json.comment_ids, function(i, id) {
                                    $.post("<?php echo Config::current()->chyrp_url; ?>/includes/ajax.php", { action: "show_comment", comment_id: id }, function(data){
                                        $(data).insertBefore("#comment_shim").hide().fadeIn("slow");
                                    }, "html");
                                });
                            }
                        }
                    }).fail( function() {
                        ChyrpComment.failed = true;
                        clearInterval(ChyrpComment.interval);
                    });
                }
            },
            edit: function(id) {
                ChyrpComment.editing++;
                $("#comment_"+id).loader();
                $.post("<?php echo Config::current()->chyrp_url; ?>/includes/ajax.php", { action: "edit_comment", comment_id: id }, function(data) {
                    if (isError(data)) return $("#comment_"+id).loader(true);
                    $("#comment_"+id).fadeOut("fast", function(){
                        $(this).loader(true);
                        $(this).empty().append(data).fadeIn("fast", function(){
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
                            $("#comment_cancel_edit_"+id).click(function(){
                                $("#comment_"+id).loader();
                                $.post("<?php echo Config::current()->chyrp_url; ?>/includes/ajax.php", { action: "show_comment", comment_id: id }, function(data){
                                    $("#comment_"+id).fadeOut("fast", function(){
                                        $(this).loader(true);
                                        $(this).replaceWith(data).fadeIn("fast");
                                    });
                                });
                            });
                            $("#comment_edit_"+id).ajaxForm({
                                beforeSubmit: function() {
                                    $("#comment_"+id).loader();
                                },
                                success: function(response) {
                                    ChyrpComment.editing--;
                                    if (isError(response)) return $("#comment_"+id).loader(true);
                                    $.post("<?php echo Config::current()->chyrp_url; ?>/includes/ajax.php", { action: "show_comment", comment_id: id, reason: "edited" }, function(data) {
                                        if (isError(data)) return $("#comment_"+id).loader(true);
                                        $("#comment_"+id).fadeOut("fast", function(){
                                            $(this).loader(true);
                                            $(this).replaceWith(data).fadeIn("fast");
                                        });
                                    }, "html");
                                }
                            });
                        })
                    });
                }, "html");
            },
            destroy: function(id) {
                ChyrpComment.notice--;
                $("#comment_"+id).loader();
                $.post("<?php echo Config::current()->chyrp_url; ?>/includes/ajax.php", { action: "delete_comment", id: id }, function(response){
                    $("#comment_"+id).loader(true);
                    if (isError(response)) return;
                    $("#comment_"+id).fadeOut("fast", function(){
                        $(this).remove();
                    });
                }, "html");
            }
        };
        $(document).ready(ChyrpComment.init);
