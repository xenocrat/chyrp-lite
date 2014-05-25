<?php
    define('JAVASCRIPT', true);
    require_once "../../../includes/common.php";
    error_reporting(0);
    header("Content-Type: application/x-javascript");
    $route = Route::current(MainController::current());
?>
<!-- --><script>
$(function(){
    if (/(write)_/.test(Route.action))
        Write.init()

    if (Route.action == "modules" || Route.action == "feathers")
        Extend.init()
})

var Write = {
    init: function(){
        this.sort_feathers();
    },
    sort_feathers: function(){
        // Make the Feathers sortable
        $("#sub_nav").children(".selected").detach().prependTo("#sub_nav");

        // Collect feather names and prepare to serialize
        var feathers = new Array();
        $("#sub_nav").children("[id]").each(function() {
            feathers[feathers.length] = $(this).attr("id");
        });
        var list = { list: feathers };

        // Update feather order with current tab order
        $.post("<?php echo $config->chyrp_url; ?>/includes/ajax.php", "action=reorder_feathers&"+ $.param(list));
    }
}

var Extend = {
    extension: {
        name: null,
        type: null
    },
    action: null,
    confirmed: null,
    init: function(){
        $(".module_toggle, .feather_toggle").click(Extend.ajax_toggle);

        if (Route.action != "modules")
            return;

        this.check_conflicts()
    },
    check_conflicts: function() {
        $(".extend li.conflict").each(function() {
            var classes = $(this).attr("class").split(" "), count = 0;
            classes.shift(); // Remove the module's safename class

            classes.remove(["conflict",
                            "depends",
                            "missing_dependency",
                            /depended_by_(.+)/,
                            /needs_(.+)/,
                            /depends_(.+)/]);

            for (i = 0; i < classes.length; i++) {
                var conflict = classes[i].replace("conflict_", "module_");
                if ($("#"+conflict).parent().attr("id") == "modules_enabled" ) {
                    count++;
                    $(this).addClass("error").find(".module_status").attr({
                        src: "<?php echo $config->chyrp_url."/admin/themes/".$config->admin_theme; ?>/images/icons/error.svg",
                        alt: "Conflicts: " + count,
                        title: "Conflicts: " + count
                    });
                }
            }
        });
    },
    ajax_reset: function() {
        Extend.extension.name = null;
        Extend.extension.type = null;
        Extend.action = null;
        Extend.confirmed = null;
    },
    ajax_toggle: function() {
        Extend.ajax_reset(); // Reset all values

        if ($(this).parents("#modules_enabled").length || $(this).parents("#feathers_enabled").length)
            Extend.action = "disable";
        else if ($(this).parents("#modules_disabled").length || $(this).parents("#feathers_disabled").length)
            Extend.action = "enable";
        else
            return true; // Failed to decide action

        if ($(this).parents("#modules_enabled").length || $(this).parents("#modules_disabled").length)
            Extend.extension.type = "module";
        else if ($(this).parents("#feathers_enabled").length || $(this).parents("#feathers_disabled").length)
            Extend.extension.type = "feather";
        else
            return true; // Failed to decide type

        Extend.extension.name = $(this).parents("li").attr("id").replace(Extend.extension.type + "_", "");

        $.post("<?php echo $config->chyrp_url; ?>/includes/ajax.php", {
            action: "check_confirm",
            check: Extend.extension.name,
            type: Extend.extension.type
        }, function(data){
            if (data != "" && Extend.action == "disable")
                Extend.confirmed = (confirm(data)) ? 1 : 0;

            $.ajax({
                type: "post",
                dataType: "json",
                url: "<?php echo $config->chyrp_url; ?>/includes/ajax.php",
                data: {
                    action: Extend.action + "_" + Extend.extension.type,
                    extension: Extend.extension.name,
                    confirm: Extend.confirmed
                },
                success: function(json) {
                    $("#" + Extend.extension.type + "_" + Extend.extension.name).detach().appendTo("#" + Extend.extension.type + "s_" + Extend.action + "d");
                    $(json.notifications).each(function(){
                        if (this == "") return
                            alert(this.replace(/<([^>]+)>\n?/gm, ""));
                    });
                },
                error: function() {
                    if (Extend.action == "enable")
                        alert("<?php echo __("There was an error enabling the extension."); ?>");
                    else
                        alert("<?php echo __("There was an error disabling the extension."); ?>");
                }
            })
        }, "text")

        Extend.check_conflicts();

        return false; // Suppress hyperlink
    }
}

<!-- --></script>
