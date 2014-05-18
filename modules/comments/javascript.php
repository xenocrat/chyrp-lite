<?php
    define('JAVASCRIPT', true);
    require_once "../../includes/common.php";
    error_reporting(0);
    header("Content-Type: application/x-javascript");
?>
<!-- --><script>
$(function(){
    if ($(".comments:not(:header)").size()) {
<?php if ($config->auto_reload_comments and $config->enable_reload_comments): ?>
        var updater = setInterval("Comment.reload()", <?php echo $config->auto_reload_comments * 1000; ?>);
<?php endif; ?>
        $("#add_comment").append($(document.createElement("input")).attr({ type: "hidden", name: "ajax", value: "true", id: "ajax" }));
        $("#add_comment").ajaxForm({ dataType: "json", resetForm: true, beforeSubmit: function() {
            $("#add_comment").loader();
        }, success: function(json){
            $.post("<?php echo $config->chyrp_url; ?>/includes/ajax.php", { action: "show_comment", comment_id: json.comment_id, reason: "added" }, function(data) {
                if ($(".comment_count").size() && $(".comment_plural").size()) {
                    var count = parseInt($(".comment_count:first").text());
                    count++;
                    $(".comment_count").text(count);
                    var plural = (count == 1) ? "" : "s";
                    $(".comment_plural").text(plural);
                }
                $("#last_comment").val(json.comment_timestamp);
                $(data).prependTo(".comments:not(:header)").hide().fadeIn("slow");
            }, "html")
        }, complete: function(){
            $("#add_comment").loader(true);
        } })
    }
<?php echo "\n"; if (!isset($config->enable_ajax) or $config->enable_ajax): ?>
    $(".comment_edit_link").live("click", function() {
        var id = $(this).attr("id").replace(/comment_edit_/, "");
        Comment.edit(id);
        return false;
    })
    $(".comment_delete_link").live("click", function() {
        var id = $(this).attr("id").replace(/comment_delete_/, "");

        Comment.notice++;

        if (!confirm("<?php echo __("Are you sure you want to delete this comment?\\n\\nIt cannot be restored if you do this.", "comments"); ?>")) {
            notice--;
            return false;
        }

        Comment.notice--;

        Comment.destroy(id);
        return false;
    })
<?php endif; ?>
})

var Comment = {
    editing: 0,
    notice: 0,
    failed: false,
    reload: function() {
        if ($(".comments:not(:header)").attr("id") == undefined) return;

        var id = $(".comments:not(:header)").attr("id").replace(/comments_/, "");
        if (Comment.editing == 0 && Comment.notice == 0 && Comment.failed != true && $(".comments:not(:header)").children().size() < <?php echo $config->comments_per_page; ?>) {
            $.ajax({ type: "post", dataType: "json", url: "<?php echo $config->chyrp_url; ?>/includes/ajax.php", data: "action=reload_comments&post_id="+id+"&last_comment="+$("#last_comment").val(), success: function(json) {
                if ( json != null ) {
                    $("#last_comment").val(json.last_comment);
                    $.each(json.comment_ids, function(i, id) {
                        $.post("<?php echo $config->chyrp_url; ?>/includes/ajax.php", { action: "show_comment", comment_id: id }, function(data){
                            $(data).prependTo(".comments:not(:header)").hide().fadeIn("slow");
                        }, "html");
                    });
                }
            } }).fail( function() { failed = true });
        }
    },
    edit: function(id) {
        Comment.editing++;
        $("#comment_"+id).loader();
        $.post("<?php echo $config->chyrp_url; ?>/includes/ajax.php", { action: "edit_comment", comment_id: id }, function(data) {
            if (isError(data)) return $("#comment_"+id).loader(true);
            $("#comment_"+id).loader(true).fadeOut("fast", function(){ $(this).empty().append(data).fadeIn("fast", function(){
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
                    $.post("<?php echo $config->chyrp_url; ?>/includes/ajax.php", { action: "show_comment", comment_id: id }, function(data){
                        $("#comment_"+id).loader(true).replaceWith(data);
                    });
                });
                $("#comment_edit_"+id).ajaxForm({ beforeSubmit: function(){
                    $("#comment_"+id).loader();
                }, success: function(response){
                    Comment.editing--;
                    if (isError(response)) return $("#comment_"+id).loader(true);
                    $.post("<?php echo $config->chyrp_url; ?>/includes/ajax.php", { action: "show_comment", comment_id: id, reason: "edited" }, function(data) {
                        if (isError(data)) return $("#comment_"+id).loader(true);
                        $("#comment_"+id).loader(true);
                        $("#comment_"+id).fadeOut("fast", function(){
                            $(this).replaceWith(data).fadeIn("fast");
                        })
                    }, "html");
                } });
            }) });
        }, "html");
    },
    destroy: function(id) {
        Comment.notice--;
        $("#comment_"+id).loader();
        $.post("<?php echo $config->chyrp_url; ?>/includes/ajax.php", { action: "delete_comment", id: id }, function(response){
            $("#comment_"+id).loader(true);
            if (isError(response)) return;

            $("#comment_"+id).fadeOut("fast", function(){
                $(this).remove();
            })

            if ($(".comment_count").size() && $(".comment_plural").size()) {
                var count = parseInt($(".comment_count:first").text());
                count--;
                $(".comment_count").text(count);
                var plural = (count == 1) ? "" : "s";
                $(".comment_plural").text(plural);
            }
        }, "html");
    }
}
<?php Trigger::current()->call("comments_javascript"); ?>
<!-- --></script>
