var ChyrpTags = {
    init: function() {
        $("form input[name='tags']").on("keyup", ChyrpTags.scan).trigger("keyup");
        $("form span.tags_select a").on("click", ChyrpTags.add);
        ChyrpTags.watch();
    },
    watch: function() {
        // Watch for DOM additions on blog pages.
        if (!!window.MutationObserver && $(".post").length) {
            var target = $(".post").last().parent()[0];
            var observer = new MutationObserver(function(mutations) {
                mutations.forEach(function(mutation) {
                    for (var i = 0; i < mutation.addedNodes.length; ++i) {
                        var item = mutation.addedNodes[i];
                        $(item).find("input[name='tags']").on("keyup", ChyrpTags.scan).trigger("keyup");
                        $(item).find("span.tags_select a").on("click", ChyrpTags.add);
                    }
                });
            });
            var config = { childList: true, subtree: true };
            observer.observe(target, config);
        }
    },
    scan: function(e) {
        $(e.target).siblings("span.tags_select").children("a.tag").each(function(){
            regexp = new RegExp("(, ?|^)" + $(this).text() + "(, ?|$)", "g");

            if ($(e.target).val().match(regexp))
                $(this).addClass("tag_added");
            else
                $(this).removeClass("tag_added");
        });
    },
    add: function(e) {
        e.preventDefault();
        var name = $(e.target).text(), tags = $(e.target).parent().siblings("input[name='tags']");

        if ($(tags).val().match("(, |^)" + name + "(, |$)")) {
            regexp = new RegExp("(, |^)" + name + "(, |$)", "g");
            $(tags).val($(tags).val().replace(regexp, function(match, before, after) {
                if (before == ", " && after == ", ")
                    return ", ";
                else
                    return "";
            }));
            $(e.target).removeClass("tag_added");
        } else {
            if ($(tags).val() == "")
                $(tags).val(name);
            else
                $(tags).val($(tags).val().replace(/(, ?)?$/, ", " + name));

            $(e.target).addClass("tag_added");
        }
    }
};
$(document).ready(ChyrpTags.init);
