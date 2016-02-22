<?php
    class SimpleMarkdown extends Modules {
        static function admin_write_wysiwyg() {
          return true;
        }

        static function admin_head() {
            $config = Config::current();

            if (!$config->enable_markdown)
                return;

            return "<!-- SimpleMDE -->\n".
                   '<link rel="stylesheet" href="'.$config->chyrp_url.'/modules/simple_markdown/simplemde.min.css" type="text/css" media="all">'."\n".
                   '<script src="'.$config->chyrp_url.'/modules/simple_markdown/simplemde.min.js" type="text/javascript" charset="utf-8"></script>'."\n".
                   '<script type="text/javascript">'."\n".
                   '    $(function() {'."\n".
                   '        $("*[data-preview=\'markup_text\']").each(function() {'."\n".
                   '            new SimpleMDE({ element: $(this)[0] });'."\n".
                   '        });'."\n".
                   '    });'."\n".
                   '</script>';
        }
    }
