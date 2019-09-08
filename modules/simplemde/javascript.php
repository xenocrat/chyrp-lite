<?php
    if (!defined('CHYRP_VERSION'))
        exit;
?>
var ChyrpSimpleMDE = {
    init: function() {
        $("textarea[data-markdown]").each(function() {
            new SimpleMDE({
            	element: $(this)[0],
            	forceSync: true,
            	autoDownloadFontAwesome: false,
            	spellChecker: false,
            	toolbar: [
                    "bold",
                    "italic",
                    "strikethrough",
                    "|",
                    "quote",
                    "unordered-list",
                    "ordered-list",
                    "|",
                    "link",
                    "image",
                    "code"
                ]
            });
        });
    }
};
$(document).ready(ChyrpSimpleMDE.init);
