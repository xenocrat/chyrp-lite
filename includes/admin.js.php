<?php
    define('JAVASCRIPT', true);
    require_once "common.php";
    error_reporting(0);
    header("Content-Type: application/x-javascript");
    $route = Route::current(MainController::current());
?>
<!-- --><script>
var Route = {
    action: "<?php echo fix($_GET['action']); ?>"
}

var site_url = "<?php echo $config->chyrp_url; ?>";

$(function(){
    // Scan AJAX responses for errors.
    $(document).ajaxComplete(function(event, request){
        var response = request ? request.responseText : null
        if (isError(response))
            alert(response.replace(/(HEY_JAVASCRIPT_THIS_IS_AN_ERROR_JUST_SO_YOU_KNOW|<([^>]+)>\n?)/gm, ""))
    })<?php echo "\n\n\n\n\n"; # Balance out the line numbers in this script and in the output to help debugging. ?>

    // Interactive behaviour.
    toggle_options();
    toggle_all();
    validate_slug();

    // Confirmations for group actions.
    if (Route.action == "edit_group") confirm_edit_group();
    if (Route.action == "delete_group") confirm_delete_group();

    // Open help text in a popup window.
    $(".help").live("click", function(){
        window.open($(this).attr("href"), "help", "status=0, scrollbars=1, location=0, menubar=0, toolbar=0, resizable=1, height=450, width=400");
        return false;
    })

    // Responsive menus
    $(".flexnav").flexNav({ 'animationSpeed' : 'fast' });
})

function toggle_all() {
    var all_checked = true;

    $(document.createElement("label")).attr("for", "toggle").text("<?php echo __("Toggle All"); ?>").appendTo("#toggler");
    $(document.createElement("input")).attr({
        type: "checkbox",
        name: "toggle",
        id: "toggle",
        "class": "checkbox"
    }).appendTo("#toggler, .toggler");

    $("#toggle").click(function(){
        $("form#new_group, form#group_edit, table").find(":checkbox").not("#toggle").each(function(){
            $(this).prop("checked", $("#toggle").prop("checked"));
        })

        $(this).parent().parent().find(":checkbox").not("#toggle").each(function(){
            $(this).prop("checked", $("#toggle").prop("checked"));
        })
    });

    // Some checkboxes are already checked when the page is loaded
    $("form#new_group, form#group_edit, table").find(":checkbox").not("#toggle").each(function(){
        if (!all_checked) return;
        all_checked = $(this).prop("checked");
    });

    $(":checkbox:not(#toggle)").click(function(){
        var action_all_checked = true;

        $("form#new_group, form#group_edit, table").find(":checkbox").not("#toggle").each(function(){
            if (!action_all_checked) return;
            action_all_checked = $(this).prop("checked");
        })

        $("#toggle").parent().parent().find(":checkbox").not("#toggle").each(function(){
            if (!action_all_checked) return;
            action_all_checked = $(this).prop("checked");
        });

        if ($("#toggler").length);
            $("#toggle").prop("checked", action_all_checked);
    });

    if ($("#toggler").length);
        $("#toggle").prop("checked", all_checked);

    $("td:has(:checkbox)").click(function(e){
        $(this).find(":checkbox").each(function(){
            if (e.target != this)
                $(this).prop("checked", !($(this).prop("checked")));
        });
    });
}

function toggle_options() {
    if ($("#more_options").size()) {
        if (Cookie.get("show_more_options") == "true")
            var more_options_text = "<?php echo __("&uarr; Fewer Options"); ?>";
        else
            var more_options_text = "<?php echo __("More Options &darr;"); ?>";

        $(document.createElement("a")).attr({
            id: "more_options_link",
            href: "javascript:void(0)"
        }).addClass("more_options_link").append(more_options_text).insertBefore("#more_options");

        if (Cookie.get("show_more_options") == null)
            $("#more_options").css("display", "none");

        $("#more_options_link").click(function(){
            if ($("#more_options").css("display") == "none") {
                $(this).empty().append("<?php echo __("&uarr; Fewer Options"); ?>");
                Cookie.set("show_more_options", "true", 30);
            } else {
                $(this).empty().append("<?php echo __("More Options &darr;"); ?>");
                Cookie.destroy("show_more_options");
            }
            $("#more_options").slideToggle();
        })
    }
}

function validate_slug() {
    $("input#slug").keyup(function(e){
        if (/^([a-zA-Z0-9\-\._:]*)$/.test($(this).val()))
            $(this).css("background", "");
        else
            $(this).css("background", "#ffdddd");
    })
}

function confirm_edit_group(msg) {
    $("form.confirm").submit(function(){
        if (!confirm("<?php echo __("You are a member of this group. Are you sure the permissions are as you want them?"); ?>"))
            return false;
    })
}

function confirm_delete_group(msg) {
    $("form.confirm").submit(function(){
        if (!confirm("<?php echo __("You are a member of this group. Are you sure you want to delete it?"); ?>"))
            return false;
    })
}

<?php $trigger->call("admin_javascript"); ?>
<!-- --></script>
