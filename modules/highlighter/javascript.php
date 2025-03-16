<?php
    if (!defined('CHYRP_VERSION'))
        exit;
?>
var ChyrpHighlighter = {
    button: <?php esce($config->module_highlighter["copy_to_clipboard"]); ?>,
    styles: {
        pre: {
            "position": "relative"
        },
        button: {
            "display": "block",
            "position": "absolute",
            "width": "fit-content",
            "inset-block-start": "1rem",
            "inset-inline-end": "1rem",
        },
        icon: {
            "display": "block"
        }
    },
    init: function(
    ) {
        $("pre > code").each(
            function(index, block) {
                hljs.highlightElement(block);
                ChyrpHighlighter.utility($(block));
            }
        );

        ChyrpHighlighter.watch();
    },
    watch: function(
    ) {
        // Watch for DOM additions on blog pages.
        if (!!window.MutationObserver && $(".post").length) {
            var target = $(".post").last().parent()[0];
            var observer = new MutationObserver(
                function(mutations) {
                    mutations.forEach(
                        function(mutation) {
                            for (var i = 0; i < mutation.addedNodes.length; ++i) {
                                var item = mutation.addedNodes[i];
                                $(item).find("pre > code").each(
                                    function(y, block) {
                                        hljs.highlightElement(block);
                                        ChyrpHighlighter.utility($(block));
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
    utility: function(
        block
    ) {
        if (ChyrpHighlighter.button) {
            block.parent().css(ChyrpHighlighter.styles.pre).append(
                $(
                    "<button>",
                    {
                        "type": "button",
                        "title": '<?php esce(__("Copy to clipboard", "highlighter")); ?>',
                        "aria-label": '<?php esce(__("Copy to clipboard", "highlighter")); ?>'
                    }
                ).css(
                    ChyrpHighlighter.styles.button
                ).click(
                    async function(e) {
                        var target = $(e.currentTarget);
                        var code = target.siblings("code").first();
                        var text = code.text();

                        try {
                            await navigator.clipboard.writeText(text);
                        } catch (err) {
                            console.log("Caught Exception: Navigator.clipboard.writeText()");

                            var selection = window.getSelection();
                            var range = document.createRange();

                            selection.removeAllRanges();
                            range.selectNodeContents(code[0]);
                            selection.addRange(range);
                            target.trigger( "blur" );
                        }
                    }
                ).append(
                    $('<?php esce($icon); ?>').css(
                        ChyrpHighlighter.styles.icon
                    )
                )
            );
        }
    }
}
$(document).ready(ChyrpHighlighter.init);
