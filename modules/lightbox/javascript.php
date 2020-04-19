<?php
    if (!defined('CHYRP_VERSION'))
        exit;
?>
var ChyrpLightbox = {
    background: "<?php echo $config->module_lightbox["background"]; ?>",
    spacing: Math.abs("<?php echo $config->module_lightbox["spacing"]; ?>"),
    protect: <?php echo($config->module_lightbox["protect"] ? "true" : "false"); ?>,
    active: false,
    styles: {
        fg: {
            "display": "block",
            "position": "absolute",
            "top": "0px",
            "left": "0px",
            "width": "auto",
            "height": "auto",
            "cursor": "default",
            "visibility": "hidden"
        },
        bg: {
            "position": "fixed",
            "top": "0px",
            "right": "0px",
            "bottom": "0px",
            "left": "0px",
            "z-index": 2147483646,
            "opacity": 0,
            "transition-property": "opacity",
            "transition-duration": "500ms",
            "cursor": "wait"
            },
        image: {
            "cursor": "url('" + Site.chyrp_url + "/modules/lightbox/images/zoom-in.svg') 6 6, pointer",
            "-webkit-tap-highlight-color": "rgba(0,0,0,0)"
        },
        black: {
            "background-color": "#000000"
        },
        grey: {
            "background-color": "#3f3f3f"
        },
        white: {
            "background-color": "#ffffff"
        },
        inherit: {
            "background-color": "inherit"
        },
    },
    init: function() {
        $.extend(ChyrpLightbox.styles.bg, ChyrpLightbox.styles[ChyrpLightbox.background]);

        $("section img").not(".suppress_lightbox").each(function() {
            $(this).on("click", ChyrpLightbox.load).css(ChyrpLightbox.styles.image);

            if (ChyrpLightbox.protect && !$(this).hasClass("suppress_protect"))
                $(this).on("contextmenu", ChyrpLightbox.prevent);
        });

        $(window).on({
            resize: ChyrpLightbox.hide,
            scroll: ChyrpLightbox.hide,
            orientationchange: ChyrpLightbox.hide,
            popstate: ChyrpLightbox.hide
        });

        ChyrpLightbox.watch();
    },
    prevent: function(e) {
        e.preventDefault();
    },
    watch: function() {
        // Watch for DOM additions on blog pages.
        if (!!window.MutationObserver && $(".post").length) {
            var target = $(".post").last().parent()[0];
            var observer = new MutationObserver(function(mutations) {
                mutations.forEach(function(mutation) {
                    for (var i = 0; i < mutation.addedNodes.length; ++i) {
                        var item = mutation.addedNodes[i];
                        $(item).find("section img").not(".suppress_lightbox").each(function() {
                            $(this).on("click", ChyrpLightbox.load).css(ChyrpLightbox.styles.image);

                            if (ChyrpLightbox.protect && !$(this).hasClass("suppress_protect"))
                                $(this).on("contextmenu", ChyrpLightbox.prevent);
                        });
                    }
                });
            });
            var config = { childList: true, subtree: true };
            observer.observe(target, config);
        }
    },
    load: function(e) {
        if (ChyrpLightbox.active == false) {
            e.preventDefault();

            var src = $(this).attr("src");
            var alt = $(this).attr("alt");
            var ref = $(this).parent("a.image_link").attr("href");

            $("<div>", {
                "id": "ChyrpLightbox-bg",
                "role": "button",
                "accesskey": "x",
                "aria-label": '<?php echo __("Close", "lightbox"); ?>'
            }).css(ChyrpLightbox.styles.bg).on("click", function(e) {
                if (e.target === e.currentTarget)
                    ChyrpLightbox.hide();
            }).append($("<img>", {
                "id": "ChyrpLightbox-fg",
                "src": src,
                "alt": alt
            }).css(ChyrpLightbox.styles.fg).on("click", function(e) {
                if (e.target === e.currentTarget) {
                    if (e.altKey && ref) {
                        if (!ChyrpLightbox.protect || ref.indexOf(Site.chyrp_url) != 0)
                            window.location.assign(ref);
                    }

                    ChyrpLightbox.hide();
                }
            }).on("load", ChyrpLightbox.show)).appendTo("body");

            ChyrpLightbox.active = true;
        }
    },
    show: function() {
        var fg = $("#ChyrpLightbox-fg"), fgWidth = fg.outerWidth(), fgHeight = fg.outerHeight();
        var bg = $("#ChyrpLightbox-bg"), bgWidth = bg.outerWidth(), bgHeight = bg.outerHeight();
        var sp = ChyrpLightbox.spacing * 2;

        if (ChyrpLightbox.protect)
            $(fg).on({
                contextmenu: function() { return false; }
            });

        while (((bgWidth - sp) < fgWidth) || ((bgHeight - sp) < fgHeight)) {
            Math.round(fgWidth = fgWidth * 0.99);
            Math.round(fgHeight = fgHeight * 0.99);
        }

        fg.css({
            "top": Math.round((bgHeight - fgHeight) / 2) + "px",
            "left": Math.round((bgWidth - fgWidth) / 2) + "px",
            "width": fgWidth + "px",
            "height": fgHeight + "px",
            "visibility": "visible",
            "cursor": "pointer"
        });
        bg.css({
            "opacity": 1,
            "cursor": "url('" + Site.chyrp_url + "/modules/lightbox/images/zoom-out.svg') 6 6, pointer"
        });
    },
    hide: function() {
        $("#ChyrpLightbox-bg").remove();
        ChyrpLightbox.active = false;
    }
}
$(document).ready(ChyrpLightbox.init);
