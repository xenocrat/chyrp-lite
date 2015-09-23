        var ChyrpComment = {
            editing: 0,
            notice: 0,
            interval: null,
            failed: false,
            reload: <?php if (Config::current()->enable_reload_comments) echo("true"); else echo("false"); ?>,
            delay: Math.abs(<?php echo(Config::current()->auto_reload_comments * 1000); ?>),
            init: function() {
                if ($("#comments").size()) {
                    if (Site.ajax && ChyrpComment.reload && ChyrpComment.delay > 0)
                        ChyrpComment.interval = setInterval(ChyrpComment.reload, ChyrpComment.delay);

                    $("#add_comment").append($(document.createElement("input")).attr({
                        type: "hidden",
                        name: "ajax",
                        value: "true",
                        id: "ajax"
                    }));
                    $("#add_comment").on( "submit", function(e){
                        if (!ChyrpComment.failed && !!window.FormData) {
                            e.preventDefault();
                            $("#add_comment").parent().loader();
                            $.ajax({
                                type: "POST",
                                url: $(this).attr("action"),
                                data: new FormData(this),
                                processData: false,
                                contentType: false,
                                dataType: "json",
                                error: ChyrpComment.panic,
                                success: function(json) {
                                    $("#add_comment").trigger('reset');
                                    $.post(Site.url + "/includes/ajax.php", {
                                        action: "show_comment",
                                        comment_id: json.comment_id,
                                        reason: "added"
                                    }, function(data) {
                                        $("#comments").attr("data-timestamp", json.comment_timestamp);
                                        $(data).insertBefore("#comment_shim").hide().fadeIn("slow");
                                    }, "html").fail(ChyrpComment.panic);
                                },
                                complete: function() {
                                    $("#add_comment").parent().loader(true);
                                }
                            });
                        }
                    });
                }

                if (Site.ajax) {
                    $("#comments").on("click", ".comment_edit_link", function(e) {
                        if (!ChyrpComment.failed) {
                            e.preventDefault();
                            var id = $(this).attr("id").replace(/comment_edit_/, "");
                            ChyrpComment.edit(id);
                        }
                    });
                    $("#comments").on("click", ".comment_delete_link", function(e) {
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
                if (ChyrpComment.editing == 0 && ChyrpComment.notice == 0 && ChyrpComment.failed != true && $(".comments.paginated").children().size() < <?php echo Config::current()->comments_per_page; ?>) {
                    $.ajax({
                        type: "post",
                        dataType: "json",
                        url: Site.url + "/includes/ajax.php",
                        data: "action=reload_comments&post_id=" + id + "&last_comment=" + $("#comments").attr("data-timestamp"),
                        error: ChyrpComment.panic
                    }).done(function(json) {
                        if (json != null) {
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
                    });
                }
            },
            edit: function(id) {
                ChyrpComment.editing++;
                $("#comment_" + id).loader();
                $.post(Site.url + "/includes/ajax.php", {
                    action: "edit_comment",
                    comment_id: id,
                    hash: Site.key
                }, function(data) {
                    if (isError(data)) {
                        ChyrpComment.panic();
                        $("#comment_" + id).loader(true);
                        return;
                    }

                    $("#comment_" + id).fadeOut("fast", function(){
                        $(this).loader(true);
                        $(this).empty().append(data).fadeIn("fast", function(){
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
                            $("#comment_cancel_edit_" + id).click(function(e){
                                e.preventDefault();

                                if (!ChyrpComment.failed) {
                                    $("#comment_" + id).loader();
                                    $.post(Site.url + "/includes/ajax.php", {
                                        action: "show_comment",
                                        comment_id: id
                                    }, function(data){
                                        $("#comment_" + id).fadeOut("fast", function(){
                                            $(this).loader(true);
                                            $(this).replaceWith(data).fadeIn("fast");
                                        });
                                    });
                                }
                            });
                            $("#comment_edit_" + id).on( "submit", function(e){
                                if (!ChyrpComment.failed && !!window.FormData) {
                                    e.preventDefault();
                                    $("#comment_" + id).loader();
                                    $.ajax({
                                        type: "POST",
                                        url: $(this).attr("action"),
                                        data: new FormData(this),
                                        processData: false,
                                        contentType: false,
                                        dataType: "html",
                                        error: ChyrpComment.panic
                                    }).done(function(response) {
                                        ChyrpComment.editing--;

                                        if (isError(response)) {
                                            ChyrpComment.panic();
                                            $("#comment_" + id).loader(true);
                                            return;
                                        }

                                        $.post(Site.url + "/includes/ajax.php", {
                                            action: "show_comment",
                                            comment_id: id,
                                            reason: "edited"
                                        }, function(data) {
                                            if (isError(data)) {
                                                ChyrpComment.panic();
                                                $("#comment_" + id).loader(true);
                                                return;
                                            }

                                            $("#comment_" + id).fadeOut("fast", function(){
                                                $(this).loader(true);
                                                $(this).replaceWith(data).fadeIn("fast");
                                            });
                                        }, "html").fail(ChyrpComment.panic);
                                    });
                                }
                            });
                        })
                    });
                }, "html").fail(ChyrpComment.panic);
            },
            destroy: function(id) {
                ChyrpComment.notice--;
                $("#comment_" + id).loader();
                $.post(Site.url + "/includes/ajax.php", {
                    action: "delete_comment",
                    id: id,
                    hash: Site.key
                }, function(response){
                    $("#comment_" + id).loader(true);

                    if (isError(response)) {
                        ChyrpComment.panic();
                        return;
                    }

                    $("#comment_" + id).fadeOut("fast", function(){
                        $(this).remove();
                    });
                }, "html").fail(ChyrpComment.panic);
            },
            panic: function() {
                ChyrpComment.failed = true;
                alert("<?php echo __("Oops! Something went wrong on this web page."); ?>");
            }
        };
        $(document).ready(ChyrpComment.init);
