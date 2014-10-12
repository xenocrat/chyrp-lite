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

    // Open help text in an iframe overlay
    Help.init();

});

var Help = {
    init: function() {
        $(".help").on("click", function(){
            Help.show($(this).attr("href"));
            return false;
        });
    },
    show: function(href) {
        $("<div>", {
            "role": "region",
        }).addClass("overlay_background").append(
            [$("<iframe>", {
                "src": href,
                "role": "contentinfo",
                "aria-label": "<?php echo __("Help", "theme"); ?>"
            }).addClass("overlay_help"),
            $("<img>", {
                "src": "<?php echo $config->chyrp_url."/admin/themes/".$config->admin_theme; ?>/images/icons/close.svg",
                "alt": "<?php echo __("Close", "theme"); ?>",
                "role": "button",
                "aria-label": "<?php echo __("Close", "theme"); ?>"
            }).addClass("overlay_close_gadget").click(function() {
                $(this).parent().remove();
            })]
        ).click(function(e) {
            if (e.target === e.currentTarget)
                $(this).remove();
        }).insertAfter("#content");
    }
}

var Write = {
    init: function() {
        this.sort_feathers();

        // Insert buttons for ajax previews
        $("*[data-preview]").each(function() {
            $("label[for='" + $(this).attr("id") + "']").attr("data-target", $(this).attr("id")).append(
                $("<img>", {
                    "src": "<?php echo $config->chyrp_url."/admin/themes/".$config->admin_theme; ?>/images/icons/magnifier.svg",
                    "alt": "<?php echo __("Preview", "theme"); ?>",
                    "title": "<?php echo __("Preview", "theme"); ?>",
                }).addClass("preview emblem").css({
                    "cursor": "pointer"
                })
            ).click(function(e){
                var content = $("#" + $(this).attr("data-target")).val();
                var filter = $("#" + $(this).attr("data-target")).attr("data-preview");
                if (content != "") {
                    e.preventDefault();
                    Write.ajax_previews(content, filter);
                }
            });
        });
    },
    sort_feathers: function() {
        // Make the selected tab the first tab
        $("#sub_nav").children(".selected").detach().prependTo("#sub_nav");

        // Collect feather names and prepare to serialize
        var feathers = new Array();
        $("#sub_nav").children("[id]").each(function() {
            feathers[feathers.length] = $(this).attr("id");
        });
        var list = { list: feathers };

        // Update feather order with current tab order
        $.post("<?php echo $config->chyrp_url; ?>/includes/ajax.php", "action=reorder_feathers&"+ $.param(list));
    },
    ajax_previews: function(content, filter) {
        $("<div>", {
            "role": "region",
        }).addClass("overlay_background").append(
            [$("<div>", {
                "role": "contentinfo",
                "aria-label": "<?php echo __("Preview", "theme"); ?>"
            }).addClass("overlay_preview css_reset").load("<?php echo $config->chyrp_url; ?>/includes/ajax.php", {
                    action: "preview",
                    content: content,
                    filter: filter
            }, function() {
                $(this).find("a").each(function() {
                    $(this).attr("target","_blank"); // Force links to spawn a new viewport
                } )
            }),
            $("<img>", {
                "src": "<?php echo $config->chyrp_url."/admin/themes/".$config->admin_theme; ?>/images/icons/close.svg",
                "alt": "<?php echo __("Close", "theme"); ?>",
                "role": "button",
                "aria-label": "<?php echo __("Close"); ?>"
            }).addClass("overlay_close_gadget").click(function() {
                $(this).parent().remove();
            })]
        ).click(function(e) {
            if (e.target === e.currentTarget)
                $(this).remove();
        }).insertAfter("#content");
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
    init: function() {
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
            alt: "<?php echo __("Blissful!", "theme"); ?>",
            title: "<?php echo __("Blissful!", "theme"); ?>"
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
                        alt: "<?php echo __("Conflicted!", "theme"); ?>",
                        title: "<?php echo __("Conflicted!", "theme"); ?>"
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
        }, function(data) {
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
                        alert("<?php echo __("There was an error enabling the extension.", "theme"); ?>");
                    else
                        alert("<?php echo __("There was an error disabling the extension.", "theme"); ?>");
                }
            })
        }, "text")

        return false; // Suppress hyperlink
    }
}

<!-- --></script>
