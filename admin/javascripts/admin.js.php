<?php
    /**
     * File: admin.js.php
     * JavaScript for core functionality and extensions.
     */
    if (!defined('CHYRP_VERSION'))
        exit;
?>
$(function() {
    toggle_all();
    validate_slug();
    validate_email();
    validate_url();
    validate_passwords();
    confirm_submit();
    auto_submit();
    solo_submit();
    Help.init();
    Write.init();
    Settings.init();
    Extend.init();
});
// Adds a master toggle to forms that have multiple checkboxes.
function toggle_all() {
    $("form[data-toggler]").each(function() {
        var all_on = true;
        var target = $(this);
        var parent = $("#" + $(this).attr("data-toggler"));
        var slaves = target.find(":checkbox");
        var master = Date.now().toString(16);

        slaves.each(function() {
            return all_on = $(this).prop("checked");
        });

        slaves.click(function(e) {
            slaves.each(function() {
                return all_on = $(this).prop("checked");
            });

            $("#" + master).prop("checked", all_on);
        });

        parent.append(
            [$("<label>", {
                "for": master
            }).text('<?php echo __("Toggle All", "admin"); ?>'),
            $("<input>", {
                "type": "checkbox",
                "name": "toggle",
                "id": master,
                "class": "checkbox"
            }).prop("checked", all_on).click(function(e) {
                slaves.prop("checked", $(this).prop("checked"));
            })]
        );
    });
}
// Validates slug fields.
function validate_slug() {
    $("input[name='slug']").keyup(function(e) {
        var slug = $(this).val();

        if (/^([a-z0-9\-]*)$/.test(slug))
            $(this).removeClass("error");
        else
            $(this).addClass("error");
    });
}
// Validates email fields.
function validate_email() {
    $("input[type='email']").keyup(function(e) {
        var text = $(this).val();

        if (text != "" && !isEmail(text))
            $(this).addClass("error");
        else
            $(this).removeClass("error");
    });
}
// Validates URL fields.
function validate_url() {
    $("input[type='url']").keyup(function(e) {
        var text = $(this).val();

        if (text != "" && !isURL(text))
            $(this).addClass("error");
        else
            $(this).removeClass("error");
    });

    $("input[type='url']").on("change", function(e) {
        var text = $(this).val();

        if (isURL(text))
            $(this).val(addScheme(text));
    });
}
// Tests the strength of #password1 and compares #password1 to #password2.
function validate_passwords() {
    var passwords = $("input[type='password']").filter(function(index) {
        var id = $(this).attr("id");
        return (!!id) ? id.match(/password[1-2]$/) : false ;
    });

    passwords.first().keyup(function(e) {
        var password = $(this).val();

        if (passwordStrength(password) > 99)
            $(this).addClass("strong");
        else
            $(this).removeClass("strong");
    });

    passwords.keyup(function(e) {
        var password1 = passwords.first().val();
        var password2 = passwords.last().val();

        if (password1 != "" && password1 != password2)
            passwords.last().addClass("error");
        else
            passwords.last().removeClass("error");
    });

    passwords.parents("form").on("submit", function(e) {
        var password1 = passwords.first().val();
        var password2 = passwords.last().val();

        if (password1 != password2) {
            e.preventDefault();
            alert('<?php echo __("Passwords do not match."); ?>');
        }
    });
}
// Asks the user to confirm form submission.
function confirm_submit() {
    $("form[data-confirm]").on("submit", function(e) {
        var text = $(this).attr("data-confirm") ||
                   '<?php echo __("Are you sure you want to proceed?", "admin"); ?>' ;

        if (!confirm(text.replace(/<[^>]+>/g, "")))
            e.preventDefault();
    });
}
// Submit a form when an element changes.
function auto_submit() {
    $("select[data-submit], input[data-submit][type='checkbox']").on("change", function(e) {
        $(this).parents("form").submit();
    });
}
// Prevent forms being submitted multiple times in a short interval.
function solo_submit() {
    $("form").on("submit.solo", function(e) {
        var last = $(this).attr("data-submitted") || 0 ;
        var when = Date.now();

        if ((when - last) < 5000) {
            e.preventDefault();
            console.log("Form submission blocked for 5 secs.");
        } else {
            $(this).attr("data-submitted", when);
        }
    });
}
var Route = {
    action: '<?php echo $route->action; ?>'
}
var Visitor = {
    token: '<?php echo authenticate(); ?>'
}
var Site = {
    url: '<?php echo addslashes($config->url); ?>',
    chyrp_url: '<?php echo addslashes($config->chyrp_url); ?>'
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
                "sandbox": "allow-same-origin allow-popups allow-popups-to-escape-sandbox"
            }).addClass("iframe_foreground").loader().on("load", function() {
                $(this).loader(true);
            }),
            $("<a>", {
                "href": "#",
                "role": "button",
                "accesskey": "x",
                "aria-label": '<?php echo __("Close", "admin"); ?>'
            }).addClass("iframe_close_gadget").click(function(e) {
                e.preventDefault();
                $(this).parent().remove();
            }).append(
                $("<img>", {
                    "src": Site.chyrp_url + '/admin/images/icons/close.svg',
                    "alt": '<?php echo __("close", "admin"); ?>'
                })
            )]
        ).click(function(e) {
            if (e.target === e.currentTarget)
                $(this).remove();
        }).insertAfter("#content");
    }
}
var Write = {
    init: function() {
        // Insert buttons for ajax previews.
        if (<?php echo($theme->file_exists("content".DIR."preview") ? "true" : "false"); ?>)
            $("#write_form *[data-preview], #edit_form *[data-preview]").each(function() {
                var target = $(this);

                $("label[for='" + target.attr("id") + "']").append(
                    $("<a>", {
                        "href": "#",
                        "role": "button",
                        "aria-label": '<?php echo __("Preview", "admin"); ?>'
                    }).addClass("emblem preview").click(function(e) {
                        var content  = target.val();
                        var field    = target.attr("name");
                        var safename = $("input#feather").val() || "page";
                        var action   = (safename == "page") ? "preview_page" : "preview_post" ;

                        e.preventDefault();

                        if (content != "")
                            Write.show(action, safename, field, content);
                        else
                            target.focus();
                    }).append(
                        $("<img>", {
                            "src": Site.chyrp_url + '/admin/images/icons/magnifier.svg',
                            "alt": '<?php echo __("preview", "admin"); ?>'
                        })
                    )
                );
            });
    },
    show: function(action, safename, field, content) {
        var uid = Date.now().toString(16);

        // Build a form targeting a named iframe.
        $("<form>", {
            "id": uid,
            "action": "<?php echo url('/', 'AjaxController'); ?>",
            "method": "post",
            "accept-charset": "UTF-8",
            "target": uid,
            "style": "display: none;"
        }).append(
            [$("<input>", {
                "type": "hidden",
                "name": "action",
                "value": action
            }),
            $("<input>", {
                "type": "hidden",
                "name": "safename",
                "value": safename
            }),
            $("<input>", {
                "type": "hidden",
                "name": "field",
                "value": field
            }),
            $("<input>", {
                "type": "hidden",
                "name": "content",
                "value": content
            }),
            $("<input>", {
                "type": "hidden",
                "name": "hash",
                "value": Visitor.token
            })]
        ).insertAfter("#content");

        // Build and display the named iframe.
        $("<div>", {
            "role": "region",
        }).addClass("iframe_background").append(
            [$("<iframe>", {
                "name": uid,
                "sandbox": "allow-same-origin allow-popups allow-popups-to-escape-sandbox"
            }).addClass("iframe_foreground").loader().on("load", function() {
                if (!!this.contentWindow.location && this.contentWindow.location != "about:blank")
                    $(this).loader(true);
            }),
            $("<a>", {
                "href": "#",
                "role": "button",
                "accesskey": "x",
                "aria-label": '<?php echo __("Close", "admin"); ?>'
            }).addClass("iframe_close_gadget").click(function(e) {
                e.preventDefault();
                $(this).parent().remove();
            }).append(
                $("<img>", {
                    "src": Site.chyrp_url + '/admin/images/icons/close.svg',
                    "alt": '<?php echo __("close", "admin"); ?>'
                })
            )]
        ).click(function(e) {
            if (e.target === e.currentTarget)
                $(this).remove();
        }).insertAfter("#content");

        // Submit the form and destroy it immediately.
        $("#" + uid).submit().remove();
    }
}
var Settings = {
    init: function() {
        $("#email_correspondence").click(function() {
            if ($(this).prop("checked") == false)
                $("#email_activation").prop("checked", false);
        });

        $("#email_activation").click(function() {
            if ($(this).prop("checked") == true)
                $("#email_correspondence").prop("checked", true);
        });

        $("form#route_settings input[name='post_url']").on("keyup", function(e) {
            $("form#route_settings code.syntax").each(function(){
                var syntax = $(this).html();
                var regexp = new RegExp("(/?|^)" + escapeRegExp(syntax) + "(/?|$)", "g");

                if ($(e.target).val().match(regexp))
                    $(this).addClass("tag_added");
                else
                    $(this).removeClass("tag_added");
            });
        }).trigger("keyup");
    }
}
var Extend = {
    init: function() {
        // Hide the confirmation checkbox and use a modal instead.
        $(".module_disabler_confirm, .feather_disabler_confirm").hide();
        $(".module_disabler, .feather_disabler").on("submit.confirm", Extend.confirm);
    },
    confirm: function(e) {
        e.preventDefault();

        var id = $(e.target).parents("li.module, li.feather").attr("id");
        var name = (!!id) ? id.replace(/^(module|feather)_/, "") : "" ;
        var text = $('label[for="confirm_' + name + '"]').html();

        // Display the modal if the text was found, and set the checkbox to the response.
        if (!!text)
            $('#confirm_' + name).prop("checked", confirm(text.replace(/<[^>]+>/g, "")));

        // Disable this handler and resubmit the form with the checkbox set accordingly.
        $(e.target).off("submit.confirm submit.solo").submit();
    }
}
<?php $trigger->call("admin_javascript"); ?>
