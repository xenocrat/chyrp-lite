var ChyrpComment = {
    editing: 0,
    notice: 0,
    interval: null,
    failed: false,
    reload: <?php echo(Config::current()->enable_reload_comments ? "true" : "false" ); ?>,
    delay: Math.abs(<?php echo(Config::current()->auto_reload_comments * 1000); ?>),
    per_page: <?php echo Config::current()->comments_per_page; ?>,
    init: function() {
        if ($("#comments").size()) {
            if (Site.ajax && ChyrpComment.reload && ChyrpComment.delay > 0)
                ChyrpComment.interval = setInterval(ChyrpComment.reload, ChyrpComment.delay);

            $("#add_comment").on("submit", function(e){
                var empties = false;
                $(this).find("input.text, textarea").not(".optional").each(function() {
                    if ($(this).val() == "") {
                        empties = true;
                        $(this).focus();
                        return false;
                    }
                });

                if (empties) {
                    e.preventDefault();
                    alert('<?php echo __("Please complete all mandatory fields in the comment form.", "comments"); ?>');
                }

                if (!isEmail($("#add_comment input[name='email']").val())) {
                    e.preventDefault();
                    alert('<?php echo __("Invalid email address.", "comments"); ?>');
                }

                var url_field = $("#add_comment input[name='url']");

                if (url_field.val() != "" && !isURL(url_field.val())) {
                    e.preventDefault();
                    alert('<?php echo __("Invalid website URL.", "comments"); ?>');
                }
            });
        }

        if (Site.ajax) {
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
        }
    },
    reload: function() {
        if (ChyrpComment.failed || $("#comments").attr("data-post") == undefined)
            return;

        var id = $("#comments").attr("data-post");

        if (ChyrpComment.editing == 0 && ChyrpComment.notice == 0 && ChyrpComment.failed != true && $(".comments.paginated").children().size() < ChyrpComment.per_page) {
            $.ajax({
                type: "post",
                dataType: "json",
                url: Site.url + "/includes/ajax.php",
                data: "action=reload_comments&post_id=" + id + "&last_comment=" + $("#comments").attr("data-timestamp"),
                error: ChyrpComment.panic,
                success: function(json) {
                    if (json.comment_ids.length > 0) {
                        $("#comments").attr("data-timestamp", json.last_comment);
                        $.each(json.comment_ids, function(i, id) {
                            $.post(Site.url + "/includes/ajax.php", {
                                action: "show_comment",
                                comment_id: id
                            }, function(data){
                                $(data).insertBefore("#comment_shim").hide().fadeIn("slow");
                            }, "html").fail(ChyrpComment.panic);
                        });
                    }
                }
            });
        }
    },
    edit: function(id) {
        ChyrpComment.editing++;
        $("#comment_" + id).loader();

        if (Site.key == "") {
            ChyrpComment.panic('<?php echo __("The comment cannot be edited because your web browser did not send proper credentials.", "comments"); ?>');
            return;
        }

        $.post(Site.url + "/includes/ajax.php", {
            action: "edit_comment",
            comment_id: id,
            hash: Site.key
        }, function(data) {
            if (isError(data)) {
                ChyrpComment.panic();
                return;
            }

            $("#comment_" + id).fadeOut("fast", function(){
                $(this).loader(true);
                $(this).empty().append(data).fadeIn("fast", function(){
                    $("#more_options_link_" + id).click(function(e){
                        e.preventDefault();

                        if ($("#more_options_" + id).css("display") == "none") {
                            $(this).empty().append('<?php echo __("&uarr; Fewer Options"); ?>');
                            $("#more_options_" + id).slideDown("slow");
                        } else {
                            $(this).empty().append('<?php echo __("More Options &darr;"); ?>');
                            $("#more_options_" + id).slideUp("slow");
                        }
                    });
                    $("#comment_edit_" + id).on("submit", function(e){
                        if (!ChyrpComment.failed && !!window.FormData) {
                            e.preventDefault();
                            var empties = false;
                            $(this).find("input.text, textarea").not(".optional").each(function() {
                                if ($(this).val() == "") {
                                    empties = true;
                                    $(this).focus();
                                    return false;
                                }
                            });

                            if (empties) {
                                alert('<?php echo __("Please complete all mandatory fields in the comment form.", "comments"); ?>');
                                return;
                            }

                            if (!isEmail($("#comment_edit_" + id + " input[name='author_email']").val())) {
                                alert('<?php echo __("Invalid email address.", "comments"); ?>');
                                return;
                            }

                            var url_field = $("#comment_edit_" + id + " input[name='author_url']");

                            if (url_field.val() != "" && !isURL(url_field.val())) {
                                alert('<?php echo __("Invalid website URL.", "comments"); ?>');
                                return;
                            }

                            $("#comment_" + id).loader();
                            $.ajax({
                                type: "POST",
                                url: $(this).attr("action"),
                                data: new FormData(this),
                                processData: false,
                                contentType: false,
                                dataType: "text",
                                error: ChyrpComment.panic,
                            }).done(ChyrpComment.updated);
                        }
                    });
                    $("#comment_cancel_edit_" + id).click(function(e){
                        e.preventDefault();

                        if (!ChyrpComment.failed) {
                            $("#comment_" + id).loader();
                            $.post(Site.url + "/includes/ajax.php", {
                                action: "show_comment",
                                context: Route.action,
                                comment_id: id,
                                reason: "cancelled"
                            }, function(data){
                                if (isError(data)) {
                                    ChyrpComment.panic();
                                    return;
                                }

                                $("#comment_" + id).fadeOut("fast", function(){
                                    $(this).loader(true);
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
    updated: function(response) {
        ChyrpComment.editing--;

        if (isError(response)) {
            ChyrpComment.panic('<?php echo __("The comment could not be updated because of an error.", "comments"); ?>');
            return;
        }

        var id = Math.abs(response);

        $.post(Site.url + "/includes/ajax.php", {
            action: "show_comment",
            context: Route.action,
            comment_id: id,
            reason: "edited"
        }, function(data) {
            if (isError(data)) {
                ChyrpComment.panic();
                return;
            }

            $("#comment_" + id).fadeOut("fast", function(){
                $(this).loader(true);
                $(this).replaceWith(data).fadeIn("fast");
            });
        }, "html").fail(ChyrpComment.panic);
    },
    destroy: function(id) {
        $("#comment_" + id).loader();

        if (Site.key == "") {
            ChyrpComment.panic('<?php echo __("The comment cannot be deleted because your web browser did not send proper credentials.", "comments"); ?>');
            return;
        }

        $.post(Site.url + "/includes/ajax.php", {
            action: "delete_comment",
            id: id,
            hash: Site.key
        }, function(response){
            if (isError(response)) {
                ChyrpComment.panic();
                return;
            }

            $("#comment_" + id).fadeOut("fast", function(){
                $(this).remove();
            });
        }, "html").fail(ChyrpComment.panic);
    },
    panic: function(message) {
        message = (typeof message === "string") ? message : '<?php echo __("Oops! Something went wrong on this web page."); ?>' ;
        ChyrpComment.failed = true;
        alert(message);
        $(".ajax_loading").loader(true);
        $("#comments form input[name='ajax']").remove();
    }
};
$(document).ready(ChyrpComment.init);
