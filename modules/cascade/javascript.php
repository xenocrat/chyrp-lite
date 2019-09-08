<?php
    if (!defined('CHYRP_VERSION'))
        exit;
?>
var ChyrpAjaxScroll = {
    busy: false,
    failed: false,
    auto: <?php echo(Config::current()->module_cascade["ajax_scroll_auto"] ? "true" : "false"); ?>,
    init: function() {
        if (ChyrpAjaxScroll.auto)
            $(window).on("scroll", window, ChyrpAjaxScroll.watch);
        else
            $("#pagination_next_page").click(ChyrpAjaxScroll.click);
    },
    click: function(e) {
        if (!ChyrpAjaxScroll.failed) {
            e.preventDefault();
            ChyrpAjaxScroll.fetch();
        }
    },
    watch: function() {
        var docViewTop = $(window).scrollTop();
        var winHeight = window.innerHeight ? window.innerHeight : $(window).height();
        var docHeight = $(document).height();
        var docViewBottom = docViewTop + winHeight;

        // Trigger fetch on scroll when 8/10 of the page has been viewed.
        if (docViewBottom >= (docHeight * 0.8))
            ChyrpAjaxScroll.fetch();
    },
    fetch: function() {
        if (!ChyrpAjaxScroll.busy && !ChyrpAjaxScroll.failed) {
            ChyrpAjaxScroll.busy = true;
            var this_post_obj = $(".post").last();
            var this_next_obj = $("#pagination_next_page");
            var this_next_url = this_next_obj.attr("href");

            if (this_next_url && this_post_obj.length) {
                $.get(this_next_url, function(data) {
                    var this_next_num = Number(this_next_url.match(/page[=\/]([0-9]+)/i)[1]);
                    var ajax_next_obj = $(data).find("#pagination_next_page");
                    var ajax_next_title = $(data).filter("title").text();

                    // Insert new posts, update the page title and location, and execute inline scripts.
                    this_post_obj.after($(data).find(".post"));
                    document.title = ajax_next_title;

                    if (!!history.replaceState)
                        history.replaceState({ "page": this_next_num }, ajax_next_title, this_next_url);

                    $(data).filter("script").each(function(){
                        $.globalEval(this.text || this.textContent || this.innerHTML || "");
                    });

                    // Replace the element #pagination_next_page if the ajax data contains a replacement.
                    if (ajax_next_obj) {
                        this_next_obj.replaceWith(ajax_next_obj);
                        ChyrpAjaxScroll.busy = false;

                        if (!ChyrpAjaxScroll.auto)
                            $("#pagination_next_page").click(ChyrpAjaxScroll.click);
                    } else {
                        // That's all folks!
                        this_next_obj.remove();
                    }
                }).fail(ChyrpAjaxScroll.panic);
            }
        }
    },
    panic: function() {
        ChyrpAjaxScroll.failed = true;
        alert('<?php echo __("Oops! Something went wrong on this web page."); ?>');
    }
};
$(document).ready(ChyrpAjaxScroll.init);
