        var ChyrpLightbox = {
            background: "<?php echo Config::current()->module_lightbox["background"]; ?>",
            spacing: Math.abs("<?php echo Config::current()->module_lightbox["spacing"]; ?>"),
            protect: <?php echo ( Config::current()->module_lightbox["protect"] ? "true" : "false" ); ?>,
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
                    "cursor": "wait",
                    "background-position": "top right",
                    "background-repeat": "no-repeat",
                    "background-size": "28px 28px"
                    },
                image: {
                    "-webkit-tap-highlight-color": "rgba(0,0,0,0)",
                    "cursor": "url('" + Site.url + "/modules/lightbox/images/zoom-in.svg') 6 6, pointer"
                },
                black: {
                    "background-color": "#000000",
                    "background-image": "url('" + Site.url + "/modules/lightbox/images/close_white.svg')"
                },
                grey: {
                    "background-color": "#3f3f3f",
                    "background-image": "url('" + Site.url + "/modules/lightbox/images/close_white.svg')"
                },
                white: {
                    "background-color": "#ffffff",
                    "background-image": "url('" + Site.url + "/modules/lightbox/images/close_grey.svg')"
                },
                inherit: {
                    "background-color": "inherit",
                    "background-image": "url('" + Site.url + "/modules/lightbox/images/close_grey.svg')"
                },
            },
            init: function() {
                $.extend( ChyrpLightbox.styles.bg, ChyrpLightbox.styles[ChyrpLightbox.background] );
                $("img.image").not(".suppress_lightbox").click(ChyrpLightbox.load).css(ChyrpLightbox.styles.image);
                if ( ChyrpLightbox.protect )
                    $("img.image").not(".suppress_lightbox").on({
                        contextmenu: function() { return false; }
                    });
                $(window).on({
                    resize: ChyrpLightbox.hide,
                    scroll: ChyrpLightbox.hide,
                    orientationchange: ChyrpLightbox.hide,
                    popstate: ChyrpLightbox.hide });
                ChyrpLightbox.watch();
            },
            watch: function() {
                // Watch for DOM additions on blog pages
                if ( !!window.MutationObserver && $(".post").length ) {
                    var target = $(".post").last().parent()[0];
                    var observer = new MutationObserver(function(mutations) {
                        mutations.forEach(function(mutation) {
                            for (var i = 0; i < mutation.addedNodes.length; ++i) {
                                var item = mutation.addedNodes[i];
                                $(item).find("img.image").not(".suppress_lightbox").click(ChyrpLightbox.load).css(ChyrpLightbox.styles.image);
                                if ( ChyrpLightbox.protect )
                                    $(item).find("img.image").not(".suppress_lightbox").on({
                                        contextmenu: function() { return false; }
                                    });
                            }
                        });
                    });
                    var config = { childList: true, subtree: true };
                    observer.observe(target, config);
                }
            },
            load: function() {
                if ( ChyrpLightbox.active == false ) {
                    var src = $(this).attr("src");
                    var alt = $(this).attr("alt");
                    var ref = $(this).parent("a.image_link").attr("href");
                    $("<div>", {
                        "id": "ChyrpLightbox-bg",
                        "role": "presentation"
                    }).css(ChyrpLightbox.styles.bg).click(function(e) {
                        if (e.target === e.currentTarget)
                            ChyrpLightbox.hide();
                    }).append($("<img>", {
                        "id": "ChyrpLightbox-fg",
                        "src": src,
                        "alt": alt,
                        "title": alt
                    }).css(ChyrpLightbox.styles.fg).click(function(e) {
                        if (e.target === e.currentTarget)
                            if (ref && ChyrpLightbox.protect == false)
                                window.location.assign(ref);
                            else
                                ChyrpLightbox.hide();
                    }).load(ChyrpLightbox.show)).appendTo("body");

                    ChyrpLightbox.active = true;
                    return false;
                }
            },
            show: function() {
                var fg = $("#ChyrpLightbox-fg"), fgWidth = fg.outerWidth(), fgHeight = fg.outerHeight();
                var bg = $("#ChyrpLightbox-bg"), bgWidth = bg.outerWidth(), bgHeight = bg.outerHeight();
                if ( ChyrpLightbox.protect )
                    $(fg).on({
                        contextmenu: function() { return false; }
                    });
                while ( ( ( bgWidth - ( ChyrpLightbox.spacing * 2 ) ) < fgWidth ) || ( ( bgHeight - ( ChyrpLightbox.spacing * 2 ) ) < fgHeight ) ) {
                    Math.round(fgWidth = fgWidth * 0.99);
                    Math.round(fgHeight = fgHeight * 0.99);
                }
                fg.css({
                    "top": Math.round( ( bgHeight - fgHeight ) / 2 ) + "px",
                    "left": Math.round( ( bgWidth - fgWidth ) / 2 ) + "px",
                    "width": fgWidth + "px",
                    "height": fgHeight + "px",
                    "visibility": "visible",
                    "cursor": "pointer"
                });
                bg.css({
                    "opacity": 1,
                    "cursor": "url('" + Site.url + "/modules/lightbox/images/zoom-out.svg') 6 6, pointer"
                });
            },
            hide: function() {
                $("#ChyrpLightbox-bg").remove();
                ChyrpLightbox.active = false;
            }
        }
        $(document).ready(ChyrpLightbox.init);
