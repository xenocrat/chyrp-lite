<?php
    define('JAVASCRIPT', true);
    require_once dirname(dirname(dirname(__FILE__))).DIRECTORY_SEPARATOR."includes".DIRECTORY_SEPARATOR."common.php";
?>
$(function() {
    // Interactive behaviour.
    toggle_all();
    toggle_options();
    toggle_correspondence();
    toggle_syntax();
    validate_slug();
    validate_email();
    validate_url();
    validate_passwords();
    confirm_submit();
    Help.init();
    Write.init();
    Extend.init();
});
function toggle_all() {
    var all_checked = true;

    $("<label>").attr("for", "toggle").text('<?php echo __("Toggle All", "theme"); ?>').appendTo("#toggler");
    $("<input>", {
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
function validate_passwords() {
    passwords = $("input[type='password']").filter(function(index) {
        var id = $(this).attr("id");
        return (!!id) ? id.match(/password[1-2]$/) : false ;
    });

    passwords.first().keyup(function(e) {
        if (passwordStrength($(this).val()) > 99)
            $(this).addClass("strong");
        else
            $(this).removeClass("strong");
    });
    passwords.keyup(function(e) {
        if (passwords.first().val() != "" && passwords.first().val() != passwords.last().val())
            passwords.last().addClass("error");
        else
            passwords.last().removeClass("error");
    });
    passwords.parents("form").on("submit", function(e) {
        if (passwords.first().val() != passwords.last().val()) {
            e.preventDefault();
            alert('<?php echo __("Passwords do not match."); ?>');
        }
    });
}
function confirm_submit() {
    $("form[data-confirm]").submit(function(e) {
        var text = $(this).attr("data-confirm") || '<?php echo __("Are you sure you want to proceed?", "theme"); ?>' ;

        if (!confirm(text.replace(/<[^>]+>/g, "")))
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
            }).addClass("iframe_foreground ajax_loading"),
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
    support: <?php echo(file_exists(THEME_DIR.DIR."content".DIR."preview.twig") ? "true" : "false"); ?>,
    wysiwyg: <?php echo($trigger->call("admin_write_wysiwyg") ? "true" : "false"); ?>,
    init: function() {
        // Insert buttons for ajax previews.
        if (Write.support && !Write.wysiwyg)
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
                            Write.preview(content, filter);
                        }
                    })
                );
            });
    },
    preview: function(content, filter) {
        var uid = Date.now().toString(16);

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
            }).addClass("iframe_foreground ajax_loading"),
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
    init: function() {
        // Hide the confirmation checkbox and use a modal instead.
        $(".module_disabler_confirm").hide();
        $(".module_disabler").on("submit.confirm", Extend.confirm);
    },
    confirm: function(e) {
        e.preventDefault();

        var id = $(e.target).parents("li.module").attr("id");
        var name = (!!id) ? id.replace(/^module_/, "") : "" ;
        var text = $('label[for="confirm_' + name + '"]').html();

        // Display the modal if the text was found, and set the checkbox to the response.
        if (!!text)
            $('#confirm_' + name).prop("checked", confirm(text.replace(/<[^>]+>/g, "")));

        // Disable this handler and resubmit the form with the checkbox set accordingly.
        $(e.target).off("submit.confirm").submit();
    }
}
<?php $trigger->call("admin_javascript"); ?>
