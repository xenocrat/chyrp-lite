<?php
    define('JAVASCRIPT', true);
    require_once dirname(dirname(dirname(__FILE__))).DIRECTORY_SEPARATOR."includes".DIRECTORY_SEPARATOR."common.php";
?>
$(function() {
    // Open help text in an iframe.
    Help.init();

    // Interactive behaviour.
    toggle_all();
    toggle_options();
    validate_slug();
    validate_email();
    validate_url();

    if (/(write)_/.test(Route.action) || /(edit)_/.test(Route.action))
        Write.init();

    if (Route.action == "modules" || Route.action == "feathers")
        Extend.init();

    // Password validation for users.
    if (Route.action == "new_user")
        validate_passwords("input[type='password']#password1", "input[type='password']#password2");

    if (Route.action == "edit_user")
        validate_passwords("input[type='password']#new_password1", "input[type='password']#new_password2");

    // Confirmation for edits affecting the user's group.
    if (Route.action == "edit_group")
        confirm_submit('<?php echo __("These changes will affect your user account. Are you sure you want to proceed?", "theme"); ?>');

    // Confirmation for edits affecting the user's account.
    if (Route.action == "edit_user")
        confirm_submit('<?php echo __("These changes will affect your user account. Are you sure you want to proceed?", "theme"); ?>');

    // Require email correspondence for activation emails.
    if (Route.action == "user_settings")
        toggle_correspondence();

    // Make the Post View URL syntax clickable.
    if (Route.action == "route_settings")
        toggle_syntax();
});
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
    if ($("#more_options").length) {
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
function toggle_syntax() {
    $("form#route_settings code.syntax").on("click", function(e) {
        var name = $(e.target).text();
        var post_url = $("form#route_settings input[name='post_url']");
        var regexp = new RegExp("(^|\\/)" + escapeRegExp(name) + "([\\/]|$)", "g");

        if (regexp.test(post_url.val())) {
            post_url.val(post_url.val().replace(regexp, function(match, before, after) {
                if (before == "/" && after == "/")
                    return "/";
                else
                    return "";
            }));
            $(e.target).removeClass("yay");
        } else {
            if (post_url.val() == "")
                post_url.val(name);
            else
                post_url.val(post_url.val().replace(/(\/?)?$/, "\/" + name));

            $(e.target).addClass("yay");
        }
    }).css("cursor", "pointer");

    $("form#route_settings input[name='post_url']").on("keyup", function(e) {
        $("form#route_settings code.syntax").each(function(){
            regexp = new RegExp("(/?|^)" + $(this).text() + "(/?|$)", "g");

            if ($(e.target).val().match(regexp))
                $(this).addClass("yay");
            else
                $(this).removeClass("yay");
        });
    }).trigger("keyup");
}
function validate_slug() {
    $("input[name='slug']").keyup(function(e) {
        if (/^([a-z0-9\-]*)$/.test($(this).val()))
            $(this).removeClass("error");
        else
            $(this).addClass("error");
    });
}
function validate_email() {
    $("input[type='email']").keyup(function(e) {
        if ($(this).val() != "" && !isEmail($(this).val()))
            $(this).addClass("error");
        else
            $(this).removeClass("error");
    });
}
function validate_url() {
    $("input[type='url']").keyup(function(e) {
        if ($(this).val() != "" && !isURL($(this).val()))
            $(this).addClass("error");
        else
            $(this).removeClass("error");
    });
}
function validate_passwords(selector_primary, selector_confirm) {
    $(selector_primary).keyup(function(e) {
        if (passwordStrength($(this).val()) > 99)
            $(this).addClass("strong");
        else
            $(this).removeClass("strong");
    });
    $(selector_primary + "," + selector_confirm).keyup(function(e) {
        if ($(selector_primary).val() != "" && $(selector_primary).val() != $(selector_confirm).val())
            $(selector_confirm).addClass("error");
        else
            $(selector_confirm).removeClass("error");
    });
    $(selector_primary).parents("form").on("submit", function(e) {
        if ($(selector_primary).val() != $(selector_confirm).val()) {
            e.preventDefault();
            Flash.warning('<?php echo __("Passwords do not match."); ?>');
        }
    });
}
function confirm_submit(message) {
    message = (typeof message === "string") ? message : '<?php echo __("Are you sure you want to proceed?", "theme"); ?>' ;

    $("form.confirm").submit(function(e) {
        if (!confirm(message))
            e.preventDefault();
    });
}
var Route = {
    action: "<?php echo fix(@$_GET['action'], true); ?>"
}
var Site = {
    url: '<?php echo $config->url; ?>',
    chyrp_url: '<?php echo $config->chyrp_url; ?>',
    key: '<?php if (same_origin() and logged_in()) echo token($_SERVER["REMOTE_ADDR"]); ?>',
    ajax: <?php echo($config->enable_ajax ? "true" : "false"); ?> 
}
var Flash = {
    last: {
        string: "",
        timestamp: Date.now()
    },
    notice: function(strings) {
        if (!(strings instanceof Array))
            strings = new Array(strings);

        for (var n = 0; n < strings.length; n++)
            Flash.alert("flash notice", strings[n]);
    },
    warning: function(strings) {
        if (!(strings instanceof Array))
            strings = new Array(strings);

        for (var w = 0; w < strings.length; w++)
            Flash.alert("flash warning", strings[w]);
    },
    message: function(strings) {
        if (!(strings instanceof Array))
            strings = new Array(strings);

        for (var m = 0; m < strings.length; m++)
            Flash.alert("flash message", strings[m]);
    },
    alert: function(classes, string) {
        var now = Date.now();

        // Retire previous flashes after an interval of 1000ms and add the new flash.
        if (string !== Flash.last.string) {
            if (now > Flash.last.timestamp + 1000)
                $("p[role='alert'].flash").stop().delay(400).fadeOut("fast", function() {
                    $(this).remove();
                });

            $("#content").prepend(
                [$("<p>", {"role": "alert"}).addClass(classes).html(string)]
            );
        }

        // Scroll to the top of the content area.
        var bodyViewTop = $("body").scrollTop();
        var flashOffset = $("#content").offset().top;

        if (bodyViewTop > flashOffset)
            $("body").stop().animate({scrollTop: flashOffset}, "fast");

        Flash.last.string = string;
        Flash.last.timestamp = now;
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
        }).addClass("iframe_background").append(
            [$("<iframe>", {
                "src": href,
                "aria-label": '<?php echo __("Help", "theme"); ?>'
            }).addClass("iframe_foreground"),
            $("<img>", {
                "src": Site.chyrp_url + '/admin/images/icons/close.svg',
                "alt": '<?php echo __("Close", "theme"); ?>',
                "role": 'button',
                "aria-label": '<?php echo __("Close", "theme"); ?>'
            }).addClass("iframe_close_gadget").click(function() {
                $(this).parent().remove();
            })]
        ).click(function(e) {
            if (e.target === e.currentTarget)
                $(this).remove();
        }).insertAfter("#content");
    }
}
var Write = {
    preview: <?php echo(file_exists(THEME_DIR.DIR."content".DIR."preview.twig") ? "true" : "false"); ?>,
    wysiwyg: <?php echo($trigger->call("admin_write_wysiwyg") ? "true" : "false"); ?>,
    init: function() {
        // Insert buttons for ajax previews.
        if (Write.preview && !Write.wysiwyg)
            $("*[data-preview]").each(function() {
                $("label[for='" + $(this).attr("id") + "']").attr("data-target", $(this).attr("id")).append(
                    $("<img>", {
                        "src": Site.chyrp_url + '/admin/images/icons/magnifier.svg',
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
    ajax_previews: function(content, filter) {
        var uid = Math.floor(Math.random()*1000000000000).toString(16);

        // Build a form targeting a named iframe.
        $("<form>", {
            "id": uid,
            "action": Site.chyrp_url + "/includes/ajax.php",
            "method": "post",
            "accept-charset": "UTF-8",
            "target": uid,
            "style": "display: none;"
        }).append(
            [$("<input>", {
                "type": "hidden",
                "name": "action",
                "value": "preview"
            }),
            $("<input>", {
                "type": "hidden",
                "name": "filter",
                "value": filter
            }),
            $("<input>", {
                "type": "hidden",
                "name": "content",
                "value": content
            }),
            $("<input>", {
                "type": "hidden",
                "name": "hash",
                "value": Site.key
            })]
        ).insertAfter("#content");

        // Build and display the named iframe.
        $("<div>", {
            "role": "region",
        }).addClass("iframe_background").append(
            [$("<iframe>", {
                "name": uid,
                "aria-label": '<?php echo __("Preview", "theme"); ?>'
            }).addClass("iframe_foreground"),
            $("<img>", {
                "src": Site.chyrp_url + '/admin/images/icons/close.svg',
                "alt": '<?php echo __("Close", "theme"); ?>',
                "role": 'button',
                "aria-label": '<?php echo __("Close", "theme"); ?>'
            }).addClass("iframe_close_gadget").click(function() {
                $(this).parent().remove();
            })]
        ).click(function(e) {
            if (e.target === e.currentTarget)
                $(this).remove();
        }).insertAfter("#content");

        // Submit the form and destroy it immediately.
        $("#" + uid).submit().remove();
    }
}
var Extend = {
    extension: {
        name: null,
        type: null
    },
    action: null,
    confirmed: null,
    confirmation: null,
    busy: false,
    failed: false,
    init: function() {
        if (Site.ajax) {
            $(".module_disabler_confirm").hide();
            $(".module_enabler, .module_disabler, .feather_enabler, .feather_disabler").on("submit", function(e) {
                if (!Extend.failed && !Extend.busy) {
                    e.preventDefault();
                    Extend.busy = true;
                    Extend.ajax_toggle(e);
                }
            });
        }

        if (Route.action == "modules")
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
        Extend.confirmation = null;
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

        if (Extend.action == null || Extend.extension.type == null) {
            Extend.panic();
            return;
        }

        Extend.extension.name = $(e.target).parents("li").attr("id").replace(Extend.extension.type + "_", "");
        Extend.confirmation = $('label[for="confirm_' + Extend.extension.name + '"]').html();

        if (!!Extend.confirmation && Extend.action == "disable")
            Extend.confirmed = (confirm(Extend.confirmation.replace(/<[^>]+>/g, ""))) ? 1 : 0 ;

        if (Site.key == "") {
            Extend.panic('<?php echo __("The extension cannot be toggled because your web browser did not send proper credentials.", "theme"); ?>');
            return;
        }

        $.ajax({
            type: "POST",
            dataType: "json",
            url: Site.chyrp_url + "/includes/ajax.php",
            data: {
                action: Extend.action,
                extension: Extend.extension.name,
                type: Extend.extension.type,
                confirm: Extend.confirmed,
                hash: Site.key
            },
            success: function(response) {
                var item = "#" + Extend.extension.type + "_" + Extend.extension.name; // Item to be moved e.g. "#module_tags".
                var list = "#" + Extend.extension.type + "s_" + Extend.action + "d";  // Destination list e.g. "#modules_enabled".

                $(item).detach().appendTo(list);

                if (Extend.extension.type == "module")
                    Extend.check_errors();

                // Display any notifications for the extension.
                if (response.data != null)
                    Flash.message(response.data);

                // Display the message returned by the responder.
                if (response.text != null)
                    Flash.notice(response.text);

                Extend.busy = false;
            },
            error: Extend.panic
        });
    },
    panic: function(message) {
        message = (typeof message === "string") ? message : '<?php echo __("Oops! Something went wrong on this web page."); ?>' ;
        Extend.failed = true;
        alert(message);
        $(".module_disabler_confirm").show();
    }
}
<?php $trigger->call("admin_javascript"); ?>
