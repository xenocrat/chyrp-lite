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
    conflicts: 0,
    init: function(){
        $(".module_enabler, .module_disabler, .feather_enabler, .feather_disabler").click(Extend.ajax_toggle);

        if (Route.action != "modules")
            return;

        this.check_conflicts()
    },
    reset_conflicts: function() {
        Extend.conflicts = 0;
        $(".extend li.error").removeClass("error").attr('class', function(i, c) {
              return c.replace(/conflict([0-9])/g, '');
        }).find(".module_status").attr({
            src: "<?php echo $config->chyrp_url."/admin/themes/".$config->admin_theme; ?>/images/icons/success.svg",
            alt: "Blissful!",
            title: "Blissful!"
        });
    },
    check_conflicts: function() {
        Extend.reset_conflicts(); // Reset all values

        $(".extend li.conflict").each(function() {
            var classes = $(this).attr("class").split(" ");

            classes.shift(); // Remove the module's safename class

            classes.remove(["conflict",
                            "depends",
                            "missing_dependency",
                            "error",
                            /depended_by_(.+)/,
                            /needs_(.+)/,
                            /depends_(.+)/,
                            /conflict([0-9])/]);

            for (i = 0; i < classes.length; i++) {
                var conflict = classes[i].replace("conflict_", "module_");
                if ($("#"+conflict).parent().attr("id") == "modules_enabled" ) {
                    Extend.conflicts++;
                    $("#"+conflict).addClass("error conflict"+Extend.conflicts);
                    $(this).addClass("error conflict"+Extend.conflicts).find(".module_status").attr({
                        src: "<?php echo $config->chyrp_url."/admin/themes/".$config->admin_theme; ?>/images/icons/error.svg",
                        alt: "Conflicted!",
                        title: "Conflicted!"
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
                    var extension = $("#" + Extend.extension.type + "_" + Extend.extension.name).detach();
                    $(extension).appendTo("#" + Extend.extension.type + "s_" + Extend.action + "d");

                    if (Extend.extension.type == "module")
                        Extend.check_conflicts();

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

        return false; // Suppress hyperlink
    }
}

<!-- --></script>
