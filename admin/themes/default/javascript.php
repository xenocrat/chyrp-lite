<?php
    define('JAVASCRIPT', true);
    require_once "../../../includes/common.php";
    error_reporting(0);
    header("Content-Type: application/x-javascript");
    $route = Route::current(MainController::current());
?>
<!-- --><script>
$(function(){
    if (/(edit|write)_/.test(Route.action))
        Write.init()

    if (Route.action == "manage_pages")
        Manage.pages.init()

    if (Route.action == "modules" || Route.action == "feathers")
        Extend.init()
})

var Write = {
    init: function(){
        this.sortable_feathers()

    },
    sortable_feathers: function(){
        // Make the Feathers sortable
        $("#sub-nav").sortable({
            axis: "x",
            placeholder: "feathers_sort",
            opacity: 0.8,
            delay: 1,
            revert: true,
            cancel: "a.no_drag, a[href$=write_page]",
            start: function(e, ui) {
                $(ui.item).find("a").click(function(){ return false })
                $(".feathers_sort").width($(ui.item).width() - 2)
            },
            update: function(e, ui){
                $(ui.item).find("a").unbind("click")
                $.post("<?php echo $config->chyrp_url; ?>/includes/ajax.php",
                       "action=reorder_feathers&"+ $("#sub-nav").sortable("serialize"))
            }
        })
    }
}

var Manage = {
    pages: {
        init: function(){
            Manage.pages.prepare_reordering()
        },
        parent_hash: function(){
            var parent_hash = ""
            $(".sort_pages li").each(function(){
                var id = $(this).attr("id").replace(/page_list_/, "")
                var parent = $(this).parent().parent() // this > #sort_pages > page_list_(id)
                var parent_id = (/page_list_/.test(parent.attr("id"))) ? parent.attr("id").replace(/page_list_/, "") : 0
                $(this).attr("parent", parent_id)
                parent_hash += "&parent["+id+"]="+parent_id
            })
            return parent_hash
        },
        prepare_reordering: function(){
            $(".sort_pages li div").css({
                background: "#f9f9f9",
                padding: ".15em .5em",
                marginBottom: ".5em",
                border: "1px solid #ddd",
                cursor: "move"
            })

            $("ul.sort_pages").tree({
                sortOn: "li",
                dropOn: "li:not(.dragging) div",
                hoverClass: "sort-hover",
                done: function(){
                    $("#content > form > ul.sort_pages").loader()
                    $.post("<?php echo $config->chyrp_url; ?>/includes/ajax.php",
                           "action=organize_pages&"+ $("ul.sort_pages").sortable("serialize") + Manage.pages.parent_hash(),
                           function(){ $("#content > form > ul.sort_pages").loader(true) })
                }
            })
        }
    }
}

var Extend = {
    init: function(){
        this.prepare_info()
        this.prepare_draggables()

        if (Route.action != "modules")
            return

        this.draw_conflicts()
        this.draw_dependencies()

        $(window).resize(function(){
            Extend.draw_conflicts()
            Extend.draw_dependencies()
        })
    },
    Drop: {
        extension: {
            classes: [],
            name: null,
            type: null
        },
        action: null,
        previous: null,
        pane: null,
        confirmed: null
    },
    prepare_info: function(){
        $(".description:not(.expanded)").wrap("<div></div>").parent().hide()
        $(".info_link").click(function(){
            $(this).parent().find(".description").parent().slideToggle("normal", Extend.redraw)
            return false
        })
    },
    prepare_draggables: function(){
        $(".enable h2, .disable h2").append(" <span class=\"sub desktop\"><?php echo __("(drag)"); ?></span>")

        $(".disable > ul > li:not(.missing_dependency), .enable > ul > li").draggable({
            zIndex: 100,
            cancel: "a",
            revert: true
        })

        $(".enable > ul, .disable > ul").droppable({
            accept: "ul.extend > li:not(.missing_dependency)",
            tolerance: "pointer",
            activeClass: "active",
            hoverClass: "hover",
            drop: Extend.handle_drop
        })

        $(".enable > ul > li, .disable > ul > li:not(.missing_dependency)").css("cursor", "move")

        Extend.equalize_lists()

        if ($(".feather").size())
            <?php $tip = _f("(tip: drag the tabs on the <a href=\\\"%s\\\">write</a> page to reorder them)",
                            array(url("/admin/?action=write"))); ?>
            $(document.createElement("small")).html("<?php echo $tip; ?>").css({
                position: "relative",
                bottom: "-1em",
                display: "block",
                textAlign: "center"
            }).appendTo(".tip_here")
    },
    handle_drop: function(ev, ui) {
        var classes = $(this).parent().attr("class").split(" ")

        Extend.Drop.pane = $(this)
        Extend.Drop.action = classes[0]
        Extend.Drop.previous = $(ui.draggable).parent().parent().attr("class").split(" ")[0]
        Extend.Drop.extension.classes = $(ui.draggable).attr("class").split(" ")
        Extend.Drop.extension.name = Extend.Drop.extension.classes[0]
        Extend.Drop.extension.type = classes[1]

        Extend.Drop.confirmed = false

        if (Extend.Drop.previous == Extend.Drop.action)
            return

        $.post("<?php echo $config->chyrp_url; ?>/includes/ajax.php", {
            action: "check_confirm",
            check: Extend.Drop.extension.name,
            type: Extend.Drop.extension.type
        }, function(data){
            if (data != "" && Extend.Drop.action == "disable")
                Extend.Drop.confirmed = (confirm(data)) ? 1 : 0

            $.ajax({
                type: "post",
                dataType: "json",
                url: "<?php echo $config->chyrp_url; ?>/includes/ajax.php",
                data: {
                    action: Extend.Drop.action + "_" + Extend.Drop.extension.type,
                    extension: Extend.Drop.extension.name,
                    confirm: Extend.Drop.confirmed
                },
                beforeSend: function(){ Extend.Drop.pane.loader() },
                success: Extend.finish_drop,
                error: function() {
                    if (Extend.Drop.action == "enable")
                        alert("<?php echo __("There was an error enabling the extension."); ?>");
                    else
                        alert("<?php echo __("There was an error disabling the extension."); ?>");

                    Extend.Drop.pane.loader(true)

                    $(ui.draggable).css({ left: 0, right: 0, top: 0, bottom: 0 }).appendTo($(".disable ul"))

                    Extend.redraw()
                }
            })
        }, "text")

        $(ui.draggable).css({ left: 0, right: 0, top: 0, bottom: 0 }).appendTo(this)

        Extend.redraw()

        return true
    },
    finish_drop: function(json){
        if (Extend.Drop.action == "enable") {
            var dependees = Extend.Drop.extension.classes.find(/depended_by_(.+)/)
            for (i = 0; i < dependees.length; i++) {
                var dependee = dependees[i].replace("depended_by", "module")

                // The module depending on this one no longer "needs" it
                $("#"+ dependee).removeClass("needs_"+ Extend.Drop.extension.name)

                // Remove from the dependee's dependency list
                $("#"+ dependee +" .dependencies_list ."+ Extend.Drop.extension.name).hide()

                if ($("#"+ dependee).attr("class").split(" ").find(/needs_(.+)/).length == 0)
                    $("#"+ dependee).find(".description").parent().hide().end().end()
                                    .draggable({
                                        zIndex: 100,
                                        cancel: "a",
                                        revert: true
                                    })
                                    .css("cursor", "move")
            }
        } else if ($(".depends_"+ Extend.Drop.extension.name).size()) {
            $(".depends_"+ Extend.Drop.extension.name).find(".description").parent().show()
            $(".depends_"+ Extend.Drop.extension.name)
                .find(".dependencies_list")
                .append($(document.createElement("li")).text(Extend.Drop.extension.name).addClass(Extend.Drop.extension.name))
                .show()
                .end()
                .find(".dependencies_message")
                .show()
                .end()
                .addClass("needs_"+ Extend.Drop.extension.name)
        }

        Extend.Drop.pane.loader(true)
        $(json.notifications).each(function(){
            if (this == "") return
            alert(this.replace(/<([^>]+)>\n?/gm, ""))
        })

        Extend.redraw()
    },
    equalize_lists: function(){
        $("ul.extend").height("auto")
        $("ul.extend").each(function(){
            if ($(".enable ul.extend").height() > $(this).height())
                $(this).css('min-height', $(".enable ul.extend").height())

            if ($(".disable ul.extend").height() > $(this).height())
                $(this).css('min-height', $(".disable ul.extend").height())
        })
    },
    redraw: function(){
        Extend.equalize_lists()
        Extend.draw_conflicts()
        Extend.draw_dependencies()
    },
    draw_conflicts: function(){
        if (!$.support.boxModel ||
            Route.action != "modules" ||
            (!$(".extend li.conflict").size()))
            return false

        $("#conflicts_canvas").remove()

        $("#header, #welcome, #sub-nav, #content a.button, .extend li, #footer, h1, h2").css({
            position: "relative",
            zIndex: 2
        })
        $("#header ul li a").css({
            position: "relative",
            zIndex: 3
        })

        $(document.createElement("canvas")).attr("id", "conflicts_canvas").prependTo("body")
        $("#conflicts_canvas").css({
            position: "absolute",
            top: 0,
            bottom: 0,
            zIndex: 1
        }).attr({ width: $(document).width(), height: $(document).height() })

        var canvas = document.getElementById("conflicts_canvas").getContext("2d")
        var conflict_displayed = []

        $(".extend li.conflict").each(function(){
            var classes = $(this).attr("class").split(" ")
            classes.shift() // Remove the module's safename class

            classes.remove(["conflict",
                            "depends",
                            "missing_dependency",
                            /depended_by_(.+)/,
                            /needs_(.+)/,
                            /depends_(.+)/,
                            /ui-draggable(-dragging)?/])

            for (i = 0; i < classes.length; i++) {
                var conflict = classes[i].replace("conflict_", "module_")

                if (conflict_displayed[$(this).attr("id")+" :: "+conflict])
                    continue

                canvas.strokeStyle = "#ef4646"
                canvas.fillStyle = "#fbe3e4"
                canvas.lineWidth = 3

                var this_status = $(this).parent().parent().attr("class").split(" ")[0] + "d"
                var conflict_status = $("#"+conflict).parent().parent().attr("class").split(" ")[0] + "d"

                if (conflict_status != this_status) {
                    var line_from_x = (conflict_status == "disabled") ?
                                      $("#"+conflict).offset().left :
                                      $("#"+conflict).offset().left + $("#"+conflict).outerWidth()
                    var line_from_y = $("#"+conflict).offset().top + 12
                    var line_to_x = (conflict_status == "enabled") ?
                                    $(this).offset().left :
                                    $(this).offset().left + $(this).outerWidth()
                    var line_to_y   = $(this).offset().top + 12

                    // Line
                    canvas.moveTo(line_from_x, line_from_y)
                    canvas.lineTo(line_to_x, line_to_y)
                    canvas.stroke()
                } else if (conflict_status == "disabled") {
                    var line_from_x = $("#"+conflict).offset().left
                    var line_from_y = $("#"+conflict).offset().top + 12
                    var line_to_x   = $(this).offset().left
                    var line_to_y   = $(this).offset().top + 12
                    var median = line_from_y + ((line_to_y - line_from_y) / 2)
                    var curve = line_from_x - 25

                    // Line
                    canvas.beginPath()
                    canvas.moveTo(line_from_x, line_from_y)
                    canvas.quadraticCurveTo(curve, median, line_to_x, line_to_y)
                    canvas.stroke()
                } else if (conflict_status == "enabled") {
                    var line_from_x = $("#"+conflict).offset().left + $("#"+conflict).outerWidth()
                    var line_from_y = $("#"+conflict).offset().top + 12
                    var line_to_x   = $(this).offset().left + $(this).outerWidth()
                    var line_to_y   = $(this).offset().top + 12
                    var median = line_from_y + ((line_to_y - line_from_y) / 2)
                    var curve = line_from_x + 25

                    // Line
                    canvas.beginPath()
                    canvas.moveTo(line_from_x, line_from_y)
                    canvas.quadraticCurveTo(curve, median, line_to_x, line_to_y)
                    canvas.stroke()
                }

                // Beginning circle
                canvas.beginPath()
                canvas.arc(line_from_x, line_from_y, 5, 0, Math.PI * 2, false)
                canvas.fill()
                canvas.stroke()

                // Ending circle
                canvas.beginPath()
                canvas.arc(line_to_x, line_to_y, 5, 0, Math.PI * 2, false)
                canvas.fill()
                canvas.stroke()

                conflict_displayed[conflict+" :: "+$(this).attr("id")] = true
            }
        })

        return true
    },
    draw_dependencies: function() {
        if (!$.support.boxModel ||
            Route.action != "modules" ||
            (!$(".extend li.depends").size()))
            return false

        $("#depends_canvas").remove()

        $("#header, #welcome, #sub-nav, #content a.button, .extend li, #footer, h1, h2").css({
            position: "relative",
            zIndex: 2
        })
        $("#header ul li a").css({
            position: "relative",
            zIndex: 3
        })

        $(document.createElement("canvas")).attr("id", "depends_canvas").prependTo("body")
        $("#depends_canvas").css({
            position: "absolute",
            top: 0,
            bottom: 0,
            zIndex: 1
        }).attr({ width: $(document).width(), height: $(document).height() })

        var canvas = document.getElementById("depends_canvas").getContext("2d")
        var dependency_displayed = []

        $(".extend li.depends").each(function(){
            var classes = $(this).attr("class").split(" ")
            classes.shift() // Remove the module's safename class

            classes.remove(["conflict",
                            "depends",
                            "missing_dependency",
                            /depended_by_(.+)/,
                            /needs_(.+)/,
                            /conflict_(.+)/,
                            /ui-draggable(-dragging)?/])

            var gradients = []

            for (i = 0; i < classes.length; i++) {
                var depend = classes[i].replace("depends_", "module_")

                if (dependency_displayed[$(this).attr("id")+" :: "+depend])
                    continue

                canvas.fillStyle = "#e4e3fb"
                canvas.lineWidth = 3

                var this_status = $(this).parent().parent().attr("class").split(" ")[0] + "d"
                var depend_status = $("#"+depend).parent().parent().attr("class").split(" ")[0] + "d"

                if (depend_status != this_status) {
                    var line_from_x = (depend_status == "disabled") ? $("#"+depend).offset().left : $("#"+depend).offset().left + $("#"+depend).outerWidth()
                    var line_from_y = $("#"+depend).offset().top + 12

                    var line_to_x = (depend_status == "enabled") ? $(this).offset().left : $(this).offset().left + $(this).outerWidth()
                    var line_to_y   = $(this).offset().top + 12
                    var height = line_to_y - line_from_y
                    var width = line_to_x - line_from_x

                    if (height <= 45)
                        gradients[i] = canvas.createLinearGradient(line_from_x, 0, line_from_x + width, 0)
                    else
                        gradients[i] = canvas.createLinearGradient(0, line_from_y, 0, line_from_y + height)

                    gradients[i].addColorStop(0, '#0052cc')
                    gradients[i].addColorStop(1, '#0096ff')

                    canvas.strokeStyle = gradients[i]

                    // Line
                    canvas.moveTo(line_from_x, line_from_y)
                    canvas.lineTo(line_to_x, line_to_y)
                    canvas.stroke()
                } else if (depend_status == "disabled") {
                    var line_from_x = $("#"+depend).offset().left + $("#"+depend).outerWidth()
                    var line_from_y = $("#"+depend).offset().top + 12
                    var line_to_x   = $(this).offset().left + $(this).outerWidth()
                    var line_to_y   = $(this).offset().top + 12
                    var median = line_from_y + ((line_to_y - line_from_y) / 2)
                    var height = line_to_y - line_from_y
                    var curve = line_from_x + 25

                    gradients[i] = canvas.createLinearGradient(0, line_from_y, 0, line_from_y + height)
                    gradients[i].addColorStop(0, '#0052cc')
                    gradients[i].addColorStop(1, '#0096ff')

                    canvas.strokeStyle = gradients[i]

                    // Line
                    canvas.beginPath()
                    canvas.moveTo(line_from_x, line_from_y)
                    canvas.quadraticCurveTo(curve, median, line_to_x, line_to_y)
                    canvas.stroke()
                } else if (depend_status == "enabled") {
                    var line_from_x = $("#"+depend).offset().left
                    var line_from_y = $("#"+depend).offset().top + 12
                    var line_to_x   = $(this).offset().left
                    var line_to_y   = $(this).offset().top + 12
                    var median = line_from_y + ((line_to_y - line_from_y) / 2)
                    var height = line_to_y - line_from_y
                    var curve = line_from_x - 25

                    gradients[i] = canvas.createLinearGradient(0, line_from_y, 0, line_from_y + height)
                    gradients[i].addColorStop(0, '#0052cc')
                    gradients[i].addColorStop(1, '#0096ff')

                    canvas.strokeStyle = gradients[i]

                    // Line
                    canvas.beginPath()
                    canvas.moveTo(line_from_x, line_from_y)
                    canvas.quadraticCurveTo(curve, median, line_to_x, line_to_y)
                    canvas.stroke()
                }

                // Beginning circle
                canvas.beginPath()
                canvas.arc(line_from_x, line_from_y, 5, 0, Math.PI * 2, false)
                canvas.fill()
                canvas.stroke()

                // Ending circle
                canvas.beginPath()
                canvas.arc(line_to_x, line_to_y, 5, 0, Math.PI * 2, false)
                canvas.fill()
                canvas.stroke()

                dependency_displayed[depend+" :: "+$(this).attr("id")] = true
            }
        })

        return true
    }
}

<!-- --></script>
