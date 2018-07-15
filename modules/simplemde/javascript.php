var ChyrpSimpleMDE = {
    init: function() {
        $("textarea[data-markdown]").each(function() {
            new SimpleMDE({ element: $(this)[0], forceSync: true });
        });
    }
};
$(document).ready(ChyrpSimpleMDE.init);
