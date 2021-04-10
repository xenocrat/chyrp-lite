<?php
    if (!defined('CHYRP_VERSION'))
        exit;
?>
var ChyrpMathJax = {
    busy: false,
    init: function() {
        ChyrpMathJax.watch();
    },
    watch: function() {
        // Watch for DOM additions on blog pages.
        if ( !!window.MutationObserver && $(".post").length ) {
            var target = $(".post").last().parent()[0];
            var observer = new MutationObserver(function(mutations) {
                if (!ChyrpMathJax.busy) {
                    ChyrpMathJax.busy = true;
                    MathJax.typesetPromise().finally(function() {
                        ChyrpMathJax.busy = false;
                    });
                }
            });
            var config = { childList: true, subtree: true };
            observer.observe(target, config);
        }
    }   
}
$(document).ready(ChyrpMathJax.init);
