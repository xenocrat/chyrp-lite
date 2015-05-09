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
    if (/(write)_/.test(Route.action))
        Write.init();

    if (Route.action == "modules" || Route.action == "feathers")
        Extend.init();

    if (Route.action == "new_user" || Route.action == "edit_user")
        Users.init();

    // Open help text in an iframe overlay
    Help.init();

});

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
});

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
            $(this).removeClass("error");
        else
            $(this).addClass("error");
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

var Users = {
    init: function() {
        $(document).ready(function() {
            $("input[type='password']#password1, input[type='password']#new_password1").keyup(function(e) {
                var score = Users.score($(this).val());
                var image = "iVBORw0KGgoAAAANSUhEUgAAAAEAAAAYCAIAAAC0rgCNAAAADklEQVR4AWPc8nA+LTEA8Y80+W/odekAAAAASUVORK5CYII=";
                $(this).css({
                    "background": "url('data:image/png;base64," + image + "') #fff no-repeat top left",
                    "background-size": (score + "% 100%")
                });
            });
            $("input[type='password']#password1, input[type='password']#password2").keyup(function(e) {
                if ( $("input[type='password']#password1").val() !== $("input[type='password']#password2").val() ) {
                    $("input[type='password']#password2").addClass("error");
                } else {
                    $("input[type='password']#password2").removeClass("error");
                }
            });
            $("input[type='password']#new_password1, input[type='password']#new_password2").keyup(function(e) {
                if ( $("input[type='password']#new_password1").val() !== $("input[type='password']#new_password2").val() ) {
                    $("input[type='password']#new_password2").addClass("error");
                } else {
                    $("input[type='password']#new_password2").removeClass("error");
                }
            });
        });
    },
    score: function(password) {
        var score = 0;
        if (!password)
            return score;

        // award every unique letter until 5 repetitions
        var letters = new Object();
        for (var i=0; i<password.length; i++) {
            letters[password[i]] = (letters[password[i]] || 0) + 1;
            score += 5.0 / letters[password[i]];
        }

        // bonus points for mixing it up
        var variations = {
            digits: /\d/.test(password),
            lower: /[a-z]/.test(password),
            upper: /[A-Z]/.test(password),
            nonWords: /\W/.test(password)
        }

        variationCount = 0;
        for (var check in variations) {
            variationCount += (variations[check] == true) ? 1 : 0;
        }
        score += (variationCount - 1) * 10;

        return parseInt(score);
    }
}

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
                "src": "<?php echo $config->chyrp_url; ?>/admin/images/icons/close.svg",
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
                    "src": "<?php echo $config->chyrp_url; ?>/admin/images/icons/magnifier.svg",
                    "alt": "(<?php echo __("Preview this field", "theme"); ?>)",
                    "title": "<?php echo __("Preview this field", "theme"); ?>",
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
                "src": "<?php echo $config->chyrp_url; ?>/admin/images/icons/close.svg",
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
        $(".extend li.error").removeClass("error");
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
                            /depends_(.+)/]);

            for (i = 0; i < classes.length; i++) {
                var conflict = classes[i].replace("conflict_", "module_");
                if ($("#"+conflict).parent().attr("id") == "modules_enabled" ) {
                    Extend.conflicts++;
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

<?php $trigger->call("admin_javascript"); ?>
<!-- --></script>
