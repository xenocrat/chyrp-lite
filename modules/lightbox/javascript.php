<?php
    if (!defined('CHYRP_VERSION'))
        exit;
?>
var ChyrpLightbox = {
    background: "<?php esce($config->module_lightbox["background"]); ?>",
    protect: <?php esce($config->module_lightbox["protect"]); ?>,
    active: false,
    styles: {
        fg: {
            "box-sizing": "border-box",
            "display": "block",
            "width": "100%",
            "height": "100%",
            "padding": "0px",
            "margin": "0px",
            "aspect-ratio": "auto",
            "object-fit": "contain",
            "background-color": "transparent"
        },
        bg: {
            "box-sizing": "border-box",
            "display": "block",
            "position": "fixed",
            "top": "0px",
            "right": "0px",
            "bottom": "0px",
            "left": "0px",
            "opacity": 0,
            "z-index": 2147483646,
            "padding": "3rem",
            "cursor": "wait",
            "background-repeat": "no-repeat",
            "background-size": "1.5rem",
            "background-position": "right 0.75rem top 0.75rem",
            "background-image": "url('" + Site.chyrp_url + "/modules/lightbox/images/close.svg')"
        },
        show: {
            "opacity": 1,
            "cursor": "pointer"
        },
        images: {
            "cursor": "pointer"
        },
        spacing: {
            "padding": Math.abs("<?php esce($config->module_lightbox["spacing"]); ?>") + "px"
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
            ChyrpLightbox.styles.fg,
            ChyrpLightbox.styles.spacing
        );

        $.extend(
            ChyrpLightbox.styles.bg,
            ChyrpLightbox.styles[ChyrpLightbox.background]
        );

        $("section img").not(".suppress_lightbox").each(
            function() {
                $(this).on(
                    "click",
                    ChyrpLightbox.load
                ).css(
                    ChyrpLightbox.styles.images
                );

                if (ChyrpLightbox.protect) {
                    if (!$(this).hasClass("suppress_protect"))
                        $(this).on(
                            "contextmenu",
                            ChyrpLightbox.prevent
                        );
                }
            }
        );

        $(window).on("popstate", ChyrpLightbox.hide);
        ChyrpLightbox.watch();
    },
    prevent: function(e) {
        e.preventDefault();
    },
    watch: function() {
        // Watch for DOM additions on blog pages.
        if (!!window.MutationObserver && $(".post").length) {
            var target = $(".post").last().parent()[0];
            var observer = new MutationObserver(
                function(mutations) {
                    mutations.forEach(
                        function(mutation) {
                            for (var i = 0; i < mutation.addedNodes.length; ++i) {
                                var item = mutation.addedNodes[i];

                                $(item).find("section img").not(".suppress_lightbox").each(
                                    function() {
                                        $(this).on(
                                            "click",
                                            ChyrpLightbox.load
                                        ).css(
                                            ChyrpLightbox.styles.images
                                        );

                                        if (ChyrpLightbox.protect) {
                                            if (!$(this).hasClass("suppress_protect"))
                                                $(this).on(
                                                    "contextmenu",
                                                    ChyrpLightbox.prevent
                                                );
                                        }
                                    }
                                );
                            }
                        }
                    );
                }
            );
            var config = {
                childList: true,
                subtree: true
            };
            observer.observe(target, config);
        }
    },
    load: function(e) {
        if (ChyrpLightbox.active == true)
            ChyrpLightbox.hide();

        e.preventDefault();

        var src = e.target.currentSrc;
        var alt = $(this).attr("alt");

        $(
            "<div>",
            {
                "id": "ChyrpLightbox-bg",
                "role": "button",
                "tabindex": "0",
                "accesskey": "x",
                "aria-label": '<?php esce(__("Stop displaying this image", "lightbox")); ?>'
            }
        ).css(
            ChyrpLightbox.styles.bg
        ).on(
            "click",
            ChyrpLightbox.hide
        ).append(
            $(
                "<img>",
                {
                    "id": "ChyrpLightbox-fg",
                    "src": src,
                    "alt": alt
                }
            ).css(
                ChyrpLightbox.styles.fg
            ).on(
                "load",
                ChyrpLightbox.show
            )
        ).appendTo("body");

        ChyrpLightbox.active = true;
    },
    show: function() {
        var fg = $("#ChyrpLightbox-fg");
        var bg = $("#ChyrpLightbox-bg");

        if (ChyrpLightbox.protect)
            $(fg).on(
                "contextmenu",
                ChyrpLightbox.prevent
            );

        bg.css(
            ChyrpLightbox.styles.show
        );
    },
    hide: function() {
        $("#ChyrpLightbox-bg").remove();
        ChyrpLightbox.active = false;
    }
}
$(document).ready(ChyrpLightbox.init);
