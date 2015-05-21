        var ChyrpTags = {
            init: function() {
                ChyrpTags.scan();
                $("#tags").on("keyup", ChyrpTags.scan);
            },
            scan: function() {
                $(".tags_select a").each(function(){
                    regexp = new RegExp("(, ?|^)"+ $(this).text() +"(, ?|$)", "g");
                    if ($("#tags").val().match(regexp))
                        $(this).addClass("tag_added");
                    else
                        $(this).removeClass("tag_added");
                });
            },
            add: function(event) {
                var name = $(event.target).text();
                if ($("#tags").val().match("(, |^)"+ name +"(, |$)")) {
                    regexp = new RegExp("(, |^)"+ name +"(, |$)", "g");
                    $("#tags").val($("#tags").val().replace(regexp, function(match, before, after) {
                        if (before == ", " && after == ", ")
                            return ", ";
                        else
                            return "";
                    }));
                    $(".tags_select a").each(function() {
                        if ($(this).text() == name)
                            $(this).removeClass("tag_added");
                    });
                } else {
                    if ($("#tags").val() == "")
                        $("#tags").val(name);
                    else
                        $("#tags").val($("#tags").val().replace(/(, ?)?$/, ", "+ name));

                    $(".tags_select a").each(function() {
                        if ($(this).text() == name)
                            $(this).addClass("tag_added");
                    });
                }
                return false; 
            }
        };
        $(document).ready(ChyrpTags.init);
