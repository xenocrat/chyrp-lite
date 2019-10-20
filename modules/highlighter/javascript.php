<?php
    if (!defined('CHYRP_VERSION'))
        exit;
?>
var ChyrpHighlighter = {
    init: function() {
        $("pre > code").each(function(index, block) {
            hljs.highlightBlock(block);
        });
        ChyrpHighlighter.watch();
    },
    watch: function() {
        // Watch for DOM additions on blog pages.
        if ( !!window.MutationObserver && $(".post").length ) {
            var target = $(".post").last().parent()[0];
            var observer = new MutationObserver(function(mutations) {
                mutations.forEach(function(mutation) {
                    for (var i = 0; i < mutation.addedNodes.length; ++i) {
                        var item = mutation.addedNodes[i];
                        $(item).find("pre > code").each(function(y, block) {
                            hljs.highlightBlock(block);
                        });
                    }
                });
            });
            var config = { childList: true, subtree: true };
            observer.observe(target, config);
        }
    }   
}
$(document).ready(ChyrpHighlighter.init);
