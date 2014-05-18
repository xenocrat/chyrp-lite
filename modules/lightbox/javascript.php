<?php
    define('JAVASCRIPT', true);
    require_once "../../includes/common.php";
    error_reporting(0);
    header("Content-Type: application/x-javascript");
?>
<!-- --><script>
        var ChyrpLightbox = {
            background: "<?php echo ( Config::current()->module_lightbox["background"] ) ?>",
            spacing: Math.abs("<?php echo ( Config::current()->module_lightbox["spacing"] ) ?>"),
            protect: <?php echo ( Config::current()->module_lightbox["protect"] ? "true" : "false" ) ?>,
            active: false,
            styles: {
                fg: {
                    "display": "block",
                    "position": "absolute",
                    "top": "0px",
                    "left": "0px",
                    "width": "auto",
                    "height": "auto",
                    "visibility": "hidden"
                },
                bg: {
                    "position": "fixed",
                    "top": "0px",
                    "right": "0px",
                    "bottom": "0px",
                    "left": "0px",
                    "z-index": 2147483647,
                    "opacity": 0,
                    "transition-property": "opacity",
                    "transition-duration": "500ms",
                    "cursor": "wait"
                },
                image: {
                    "-webkit-tap-highlight-color": "rgba(0,0,0,0)",
                    "cursor": "url('<?php echo ( Config::current()->chyrp_url."/modules/lightbox/images/zoom-in.svg") ?>'), pointer"
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
                if ( isNaN(ChyrpLightbox.spacing) ) ChyrpLightbox.spacing = 24;
                $.extend( ChyrpLightbox.styles.bg, ChyrpLightbox.styles[ChyrpLightbox.background] );
                $("img.image").not(".suppress_lightbox").click(ChyrpLightbox.load).css(ChyrpLightbox.styles.image);
                if ( ChyrpLightbox.protect ) $("img.image").not(".suppress_lightbox").on({ contextmenu: function() { return false } });
                $(window).on({ resize: ChyrpLightbox.hide, orientationchange: ChyrpLightbox.hide, popstate: ChyrpLightbox.hide });
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
                                if ( ChyrpLightbox.protect ) $(item).find("img.image").not(".suppress_lightbox").on({ contextmenu: function() { return false } });
                            }
                        });
                    });
                    var config = { childList: true, subtree: true };
                    observer.observe(target, config);
                }
            },
            load: function() {
                if ( ChyrpLightbox.active == false ) {
                    var src = $(this).attr('src'), href = $(this).parent().attr('href'), alt = $(this).attr('alt');
                    $("<div>", {
                        "id": "ChyrpLightbox-bg",
                        "role": "img",
                        "aria-label": alt
                    }).css(ChyrpLightbox.styles.bg).click(ChyrpLightbox.hide).append($("<img>", {
                        "id": "ChyrpLightbox-fg",
                        "src": href || src, // Load original (Photo Feather)
                        "alt": alt
                    }).css(ChyrpLightbox.styles.fg).load(ChyrpLightbox.show).error(function() {
                        $(this).off('error'); this.src = src;
                    })).appendTo("body");
                    ChyrpLightbox.active = true;
                    return false;
                }
            },
            show: function() {
                var fg = $("#ChyrpLightbox-fg"), fgWidth = fg.outerWidth(), fgHeight = fg.outerHeight();
                var bg = $("#ChyrpLightbox-bg"), bgWidth = bg.outerWidth(), bgHeight = bg.outerHeight();
                if ( ChyrpLightbox.protect ) $(fg).on({ contextmenu: function() { return false } });
                while ( ( ( bgWidth - ( ChyrpLightbox.spacing * 2 ) ) < fgWidth ) || ( ( bgHeight - ( ChyrpLightbox.spacing * 2 ) ) < fgHeight ) ) {
                    Math.round(fgWidth = fgWidth * 0.99);
                    Math.round(fgHeight = fgHeight * 0.99);
                }
                fg.css({
                    "top": Math.round( ( bgHeight - fgHeight ) / 2 ) + 'px',
                    "left": Math.round( ( bgWidth - fgWidth ) / 2 ) + 'px',
                    "width": fgWidth + 'px',
                    "height": fgHeight + 'px',
                    "visibility": 'visible'
                });
                $("<img>", {
                    "src": "<?php echo ( Config::current()->chyrp_url."/modules/lightbox/images/minimize.svg") ?>",
                    "alt": "<?php echo ( __("Minimize", "lightbox") ) ?>",
                    "title": "<?php echo ( __("Minimize", "lightbox") ) ?>",
                    "role": "button",
                    "aria-label": "<?php echo ( __("Minimize", "lightbox") ) ?>"
                }).css({
                    "display": "block",
                    "position": "absolute",
                    "top": Math.round( ( ( bgHeight - fgHeight ) / 2 ) + 8 ) + 'px',
                    "right": Math.round( ( ( bgWidth - fgWidth ) / 2 ) + 8 ) + 'px',
                    "cursor": "pointer"
                }).click(ChyrpLightbox.hide).appendTo("#ChyrpLightbox-bg");
                bg.css({
                    "opacity": 1,
                    "cursor": "url('<?php echo ( Config::current()->chyrp_url."/modules/lightbox/images/zoom-out.svg") ?>'), pointer"
                });
            },
            hide: function() {
                $("#ChyrpLightbox-bg").remove();
                ChyrpLightbox.active = false;
            }
        }
        $(document).ready(ChyrpLightbox.init);
<!-- --></script>
