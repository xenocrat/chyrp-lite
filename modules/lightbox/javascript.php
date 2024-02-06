<?php
    if (!defined('CHYRP_VERSION'))
        exit;
?>
var ChyrpLightbox = {
    background: "<?php esce($config->module_lightbox["background"]); ?>",
    spacing: 48 + Math.abs("<?php esce($config->module_lightbox["spacing"]); ?>"),
    protect: <?php esce($config->module_lightbox["protect"]); ?>,
    active: false,
    styles: {
        fg: {
            "display": "block",
            "position": "absolute",
            "top": "0px",
            "left": "0px",
            "width": "auto",
            "height": "auto",
            "cursor": "crosshair",
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
            "cursor": "wait",
            "background-repeat": "no-repeat",
            "background-position": "right 12px top 12px",
            "background-image": "url('" + Site.chyrp_url + "/modules/lightbox/images/close.svg')"
        },
        image: {
            "cursor": "pointer",
        },
        black: {
            "background-color": "#2f2f2f"
        },
        grey: {
            "background-color": "#7f7f7f"
        },
        white: {
            "background-color": "#ffffff"
        },
        inherit: {
            "background-color": "inherit"
        },
    },
    init: function() {
        $.extend(
            ChyrpLightbox.styles.bg,
            ChyrpLightbox.styles[ChyrpLightbox.background]
        );

        $("section img").not(".suppress_lightbox").each(function() {
            $(this).on("click", ChyrpLightbox.load).css(
                ChyrpLightbox.styles.image
            );

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
                            $(this).on("click", ChyrpLightbox.load).css(
                                ChyrpLightbox.styles.image
                            );

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

            $("<div>", {
                "id": "ChyrpLightbox-bg",
                "role": "button",
                "tabindex": "0",
                "accesskey": "x",
                "aria-label": '<?php esce(__("Stop displaying this image", "lightbox")); ?>'
            }).css(
                ChyrpLightbox.styles.bg
            ).on("click", function(e) {
                if (e.target === e.currentTarget)
                    ChyrpLightbox.hide();
            }).append(
                $("<img>", {
                    "id": "ChyrpLightbox-fg",
                    "src": src,
                    "alt": alt
                }).css(
                    ChyrpLightbox.styles.fg
                ).on("load", ChyrpLightbox.show)
            ).appendTo("body");

            ChyrpLightbox.active = true;
        }
    },
    show: function() {
        var fg = $("#ChyrpLightbox-fg");
        var fgWidth = fg.outerWidth();
        var fgHeight = fg.outerHeight();
        var bg = $("#ChyrpLightbox-bg");
        var bgWidth = bg.outerWidth();
        var bgHeight = bg.outerHeight();
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
        });
        bg.css({
            "opacity": 1,
            "cursor": "pointer"
        });
    },
    hide: function() {
        $("#ChyrpLightbox-bg").remove();
        ChyrpLightbox.active = false;
    }
}
$(document).ready(ChyrpLightbox.init);
