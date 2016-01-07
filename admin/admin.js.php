<?php
    define('JAVASCRIPT', true);
    require_once dirname(dirname(__FILE__)).DIRECTORY_SEPARATOR."includes".DIRECTORY_SEPARATOR."common.php";
?>
$(function() {
    if (/(write)_/.test(Route.action) || /(edit)_/.test(Route.action))
        Write.init();

    if (Route.action == "modules" || Route.action == "feathers")
        Extend.init();

    if (Route.action == "new_user")
        Passwords.check("input[type='password']#password1", "input[type='password']#password2");

    if (Route.action == "edit_user")
        Passwords.check("input[type='password']#new_password1", "input[type='password']#new_password2");

    // Open help text in an overlay.
    Help.init();

    // Interactive behaviour.
    toggle_options();
    toggle_all();
    validate_slug();

    if (Route.action == "user_settings")
        toggle_correspondence();

    // Confirmations for group actions.
    if (Route.action == "edit_group")
        confirm_edit_group();

    if (Route.action == "delete_group")
        confirm_delete_group();
});
var Route = {
    action: "<?php echo fix($_GET['action']); ?>"
}
var Site = {
    url: '<?php echo $config->chyrp_url; ?>',
    key: '<?php if (logged_in() and strpos($_SERVER["HTTP_REFERER"], $config->url) === 0) echo token($_SERVER["REMOTE_ADDR"]); ?>',
    ajax: <?php echo($config->enable_ajax ? "true" : "false"); ?>

}
function toggle_all() {
    var all_checked = true;

    $(document.createElement("label")).attr("for", "toggle").text('<?php echo __("Toggle All", "theme"); ?>').appendTo("#toggler");
    $(document.createElement("input")).attr({
        "type": "checkbox",
        "name": "toggle",
        "id": "toggle",
        "class": "checkbox"
    }).appendTo("#toggler, .toggler");

    $("#toggle").click(function() {
        $("form#new_group, form#group_edit, table").find(":checkbox").not("#toggle").each(function() {
            $(this).prop("checked", $("#toggle").prop("checked"));
        });

        $(this).parent().parent().find(":checkbox").not("#toggle").each(function() {
            $(this).prop("checked", $("#toggle").prop("checked"));
        });
    });

    // Some checkboxes are already checked when the page is loaded.
    $("form#new_group, form#group_edit, table").find(":checkbox").not("#toggle").each(function() {
        if (!all_checked)
            return;

        all_checked = $(this).prop("checked");
    });

    $(":checkbox:not(#toggle)").click(function() {
        var action_all_checked = true;

        $("form#new_group, form#group_edit, table").find(":checkbox").not("#toggle").each(function() {
            if (!action_all_checked)
                return;

            action_all_checked = $(this).prop("checked");
        });

        $("#toggle").parent().parent().find(":checkbox").not("#toggle").each(function() {
            if (!action_all_checked)
                return;

            action_all_checked = $(this).prop("checked");
        });

        if ($("#toggler").length);
            $("#toggle").prop("checked", action_all_checked);
    });

    if ($("#toggler").length);
        $("#toggle").prop("checked", all_checked);

    $("td:has(:checkbox)").click(function(e) {
        $(this).find(":checkbox").each(function() {
            if (e.target != this)
                $(this).prop("checked", !($(this).prop("checked")));
        });
    });
}
function toggle_options() {
    if ($("#more_options").size()) {
        if (Cookie.get("show_more_options") == "true")
            var more_options_text = '<?php echo __("&uarr; Fewer Options", "theme"); ?>';
        else
            var more_options_text = '<?php echo __("More Options &darr;", "theme"); ?>';

        $(document.createElement("a")).attr({
            "id": "more_options_link",
            "href": "#"
        }).addClass("more_options_link").append(more_options_text).insertBefore("#more_options");

        if (Cookie.get("show_more_options") == null)
            $("#more_options").css("display", "none");

        $("#more_options_link").click(function(e) {
            e.preventDefault();

            if ($("#more_options").css("display") == "none") {
                $(this).empty().append('<?php echo __("&uarr; Fewer Options", "theme"); ?>');
                Cookie.set("show_more_options", "true", 30);
            } else {
                $(this).empty().append('<?php echo __("More Options &darr;", "theme"); ?>');
                Cookie.destroy("show_more_options");
            }
            $("#more_options").slideToggle();
        });
    }
}
function toggle_correspondence() {
    $("#email_correspondence").click(function() {
        if ($(this).prop("checked") == false)
            $("#email_activation").prop("checked", false);
    });
    $("#email_activation").click(function() {
        if ($(this).prop("checked") == true)
            $("#email_correspondence").prop("checked", true);
    });
}
function validate_slug() {
    $("input[name='slug']").keyup(function(e) {
        if (/^([a-zA-Z0-9\-\._:]*)$/.test($(this).val()))
            $(this).removeClass("error");
        else
            $(this).addClass("error");
    });
}
function confirm_edit_group(msg) {
    $("form.confirm").submit(function(e) {
        if (!confirm('<?php echo __("You are a member of this group. Are you sure the permissions are as you want them?", "theme"); ?>'))
            e.preventDefault();
    });
}
function confirm_delete_group(msg) {
    $("form.confirm").submit(function(e) {
        if (!confirm('<?php echo __("You are a member of this group. Are you sure you want to delete it?", "theme"); ?>'))
            e.preventDefault();
    });
}
var Passwords = {
    check: function(selector_primary, selector_confirm) {
        $(selector_primary).keyup(function(e) {
            if (passwordStrength($(this).val()) < 75)
                $(this).removeClass("strong");
            else
                $(this).addClass("strong");
        });
        $(selector_primary).parents("form").on("submit", function(e) {
            if ($(selector_primary).val() !== $(selector_confirm).val()) {
                e.preventDefault();
                alert('<?php echo __("Passwords do not match."); ?>');
            }
        });
    }
}
var Help = {
    init: function() {
        $(".help").on("click", function(e) {
            e.preventDefault();
            Help.show($(this).attr("href"));
        });
    },
    show: function(href) {
        $("<div>", {
            "role": "region",
        }).addClass("overlay_background").append(
            [$("<div>", {
                "role": 'contentinfo',
                "aria-label": '<?php echo __("Help", "theme"); ?>'
            }).addClass("overlay_foreground").load(href + "&ajax=1", null, function(response) {
                if (isError(response))
                    $(this).text('<?php echo __("Oops! Something went wrong on this web page."); ?>');

                $(this).find("a").each(function() {
                    $(this).attr("target","_blank"); // Force links to spawn a new viewport.
                })
            }),
            $("<img>", {
                "src": Site.url + '/admin/images/icons/close.svg',
                "alt": '<?php echo __("Close", "theme"); ?>',
                "role": 'button',
                "aria-label": '<?php echo __("Close", "theme"); ?>'
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
        if (/(write)_/.test(Route.action))
            Write.sort_feathers();

        // Insert buttons for ajax previews.
        $("*[data-preview]").each(function() {
            $("label[for='" + $(this).attr("id") + "']").attr("data-target", $(this).attr("id")).append(
                $("<img>", {
                    "src": Site.url + '/admin/images/icons/magnifier.svg',
                    "alt": '(<?php echo __("Preview this field", "theme"); ?>)',
                    "title": '<?php echo __("Preview this field", "theme"); ?>',
                }).addClass("emblem preview").click(function(e) {
                    var content = $("#" + $(this).parent().attr("data-target")).val();
                    var filter = $("#" + $(this).parent().attr("data-target")).attr("data-preview");
                    if (content != "") {
                        e.preventDefault();
                        Write.ajax_previews(content, filter);
                    }
                })
            );
        });
    },
    sort_feathers: function() {
        // Make the selected tab the first tab.
        $("#sub_nav").children(".selected").detach().prependTo("#sub_nav");

        var feathers = new Array();
        $("#sub_nav").children("[id]").each(function() {
            feathers[feathers.length] = $(this).attr("id");
        });

        // Update feather order with current tab order.
        $.post(Site.url + "/includes/ajax.php", {
            action: "reorder_feathers",
            list: feathers
        });
    },
    ajax_previews: function(content, filter) {
        $("<div>", {
            "role": "region",
        }).addClass("overlay_background").append(
            [$("<div>", {
                "role": 'contentinfo',
                "aria-label": '<?php echo __("Preview", "theme"); ?>'
            }).addClass("overlay_foreground").load(Site.url + "/includes/ajax.php", {
                    action: "preview",
                    content: content,
                    filter: filter
            }, function(response) {
                if (isError(response))
                    $(this).text('<?php echo __("Oops! Something went wrong on this web page."); ?>');

                $(this).find("a").each(function() {
                    $(this).attr("target","_blank"); // Force links to spawn a new viewport.
                })
            }),
            $("<img>", {
                "src": Site.url + '/admin/images/icons/close.svg',
                "alt": '<?php echo __("Close", "theme"); ?>',
                "role": 'button',
                "aria-label": '<?php echo __("Close", "theme"); ?>'
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
    failed: false,
    init: function() {
        if (Site.ajax)
            $(".module_enabler, .module_disabler, .feather_enabler, .feather_disabler").click(function(e) {
                if (!Extend.failed) {
                    e.preventDefault();
                    Extend.ajax_toggle(e);
                }
            });

        if (Route.action != "modules")
            return;

        Extend.check_errors();
    },
    reset_errors: function() {
        $(".modules li.error").removeClass("error");
    },
    check_errors: function() {
        Extend.reset_errors(); // Reset all values.

        $(".modules li.conflicts").each(function() {
            var classes = $(this).attr("class").split(" ");

            classes.shift(); // Remove the module's safename class.

            classes.remove(["conflicts",
                            "dependencies",
                            "missing_dependency",
                            "error",
                            /needed_by_(.+)/,
                            /needs_(.+)/]);

            for (i = 0; i < classes.length; i++) {
                var conflict = classes[i].replace("conflict_", "module_");

                if ($("#"+conflict).parent().attr("id") == "modules_enabled") {
                    $(this).addClass("error");
                }
            }
        });

        $(".modules li.dependencies").each(function() {
            var classes = $(this).attr("class").split(" ");

            classes.shift(); // Remove the module's safename class.

            if (classes.indexOf("missing_dependency") >= 0) {
                $(this).addClass("error");
                return;
            }

            classes.remove(["conflicts",
                            "dependencies",
                            "missing_dependency",
                            "error",
                            /needed_by_(.+)/,
                            /conflict_(.+)/]);

            for (i = 0; i < classes.length; i++) {
                var dependency = classes[i].replace("needs_", "module_");

                if ($("#"+dependency).parent().attr("id") == "modules_disabled") {
                    $(this).addClass("error");
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
    ajax_toggle: function(e) {
        Extend.ajax_reset(); // Reset all values.

        if ($(e.target).parents("#modules_enabled").length || $(e.target).parents("#feathers_enabled").length)
            Extend.action = "disable";
        else if ($(e.target).parents("#modules_disabled").length || $(e.target).parents("#feathers_disabled").length)
            Extend.action = "enable";

        if ($(e.target).parents("#modules_enabled").length || $(e.target).parents("#modules_disabled").length)
            Extend.extension.type = "module";
        else if ($(e.target).parents("#feathers_enabled").length || $(e.target).parents("#feathers_disabled").length)
            Extend.extension.type = "feather";

        Extend.extension.name = $(e.target).parents("li").attr("id").replace(Extend.extension.type + "_", "");

        if (Extend.action == null || Extend.extension.type == null || !Extend.extension.name) {
            Extend.panic();
            return;
        }

        $.post(Site.url + "/includes/ajax.php", {
            action: "check_confirm",
            check: Extend.extension.name,
            type: Extend.extension.type,
        }, function(data) {
            if (data != "" && Extend.action == "disable")
                Extend.confirmed = (confirm(data)) ? 1 : 0;

            $.ajax({
                type: "POST",
                dataType: "json",
                url: Site.url + "/includes/ajax.php",
                data: {
                    action: Extend.action + "_" + Extend.extension.type,
                    extension: Extend.extension.name,
                    confirm: Extend.confirmed,
                    hash: Site.key
                },
                success: function(json) {
                    var extension = $("#" + Extend.extension.type + "_" + Extend.extension.name).detach();
                    $(extension).appendTo("#" + Extend.extension.type + "s_" + Extend.action + "d");

                    if (Extend.extension.type == "module")
                        Extend.check_errors();

                    $(json.notifications).each(function() {
                        if (this == "") return
                            alert(this.replace(/<([^>]+)>\n?/gm, ""));
                    });
                },
                error: function() {
                    if (Extend.action == "enable")
                        alert('<?php echo __("There was an error enabling the extension.", "theme"); ?>');
                    else
                        alert('<?php echo __("There was an error disabling the extension.", "theme"); ?>');
                }
            })
        }, "text").fail(Extend.panic);
    },
    panic: function() {
        Extend.failed = true;
        alert('<?php echo __("Oops! Something went wrong on this web page."); ?>');
    }
}
<?php $trigger->call("admin_javascript"); ?>
