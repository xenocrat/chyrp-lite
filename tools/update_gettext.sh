#!/bin/sh
ruby ./tools/gettext.rb ./                                                   > ./includes/locale/en_US/LC_MESSAGES/chyrp.pot

ruby ./tools/gettext.rb ./feathers/audio/       --domain=audio               > ./feathers/audio/locale/en_US/LC_MESSAGES/audio.pot
ruby ./tools/gettext.rb ./feathers/link/        --domain=link                > ./feathers/link/locale/en_US/LC_MESSAGES/link.pot
ruby ./tools/gettext.rb ./feathers/photo/       --domain=photo               > ./feathers/photo/locale/en_US/LC_MESSAGES/photo.pot
ruby ./tools/gettext.rb ./feathers/quote/       --domain=quote               > ./feathers/quote/locale/en_US/LC_MESSAGES/quote.pot
ruby ./tools/gettext.rb ./feathers/text/        --domain=text                > ./feathers/text/locale/en_US/LC_MESSAGES/text.pot
ruby ./tools/gettext.rb ./feathers/video/       --domain=video               > ./feathers/video/locale/en_US/LC_MESSAGES/video.pot
ruby ./tools/gettext.rb ./feathers/uploader/    --domain=uploader            > ./feathers/uploader/locale/en_US/LC_MESSAGES/uploader.pot

ruby ./tools/gettext.rb ./modules/cacher/       --domain=cacher              > ./modules/cacher/locale/en_US/LC_MESSAGES/cacher.pot
ruby ./tools/gettext.rb ./modules/cascade/      --domain=cascade             > ./modules/cascade/locale/en_US/LC_MESSAGES/cascade.pot
ruby ./tools/gettext.rb ./modules/categorize/   --domain=categorize          > ./modules/categorize/locale/en_US/LC_MESSAGES/categorize.pot
ruby ./tools/gettext.rb ./modules/comments/     --domain=comments            > ./modules/comments/locale/en_US/LC_MESSAGES/comments.pot
ruby ./tools/gettext.rb ./modules/easy_embed/   --domain=easy_embed          > ./modules/easy_embed/locale/en_US/LC_MESSAGES/easy_embed.pot
ruby ./tools/gettext.rb ./modules/highlighter/  --domain=highlighter         > ./modules/highlighter/locale/en_US/LC_MESSAGES/highlighter.pot
ruby ./tools/gettext.rb ./modules/lightbox/     --domain=lightbox            > ./modules/lightbox/locale/en_US/LC_MESSAGES/lightbox.pot
ruby ./tools/gettext.rb ./modules/likes/        --domain=likes               > ./modules/likes/locale/en_US/LC_MESSAGES/likes.pot
ruby ./tools/gettext.rb ./modules/maptcha/      --domain=maptcha             > ./modules/maptcha/locale/en_US/LC_MESSAGES/maptcha.pot
ruby ./tools/gettext.rb ./modules/migrator/     --domain=migrator            > ./modules/migrator/locale/en_US/LC_MESSAGES/migrator.pot
ruby ./tools/gettext.rb ./modules/pingable/     --domain=pingable            > ./modules/pingable/locale/en_US/LC_MESSAGES/pingable.pot
ruby ./tools/gettext.rb ./modules/read_more/    --domain=read_more           > ./modules/read_more/locale/en_US/LC_MESSAGES/read_more.pot
ruby ./tools/gettext.rb ./modules/rights/       --domain=rights              > ./modules/rights/locale/en_US/LC_MESSAGES/rights.pot
ruby ./tools/gettext.rb ./modules/simplemde/    --domain=simplemde           > ./modules/simplemde/locale/en_US/LC_MESSAGES/simplemde.pot
ruby ./tools/gettext.rb ./modules/sitemap/      --domain=sitemap             > ./modules/sitemap/locale/en_US/LC_MESSAGES/sitemap.pot
ruby ./tools/gettext.rb ./modules/tags/         --domain=tags                > ./modules/tags/locale/en_US/LC_MESSAGES/tags.pot
ruby ./tools/gettext.rb ./modules/post_views/   --domain=post_views          > ./modules/post_views/locale/en_US/LC_MESSAGES/post_views.pot

ruby ./tools/gettext.rb ./themes/blossom/       --domain=blossom     --theme > ./themes/blossom/locale/en_US/LC_MESSAGES/blossom.pot
ruby ./tools/gettext.rb ./themes/sparrow/       --domain=sparrow     --theme > ./themes/sparrow/locale/en_US/LC_MESSAGES/sparrow.pot
ruby ./tools/gettext.rb ./themes/topaz/         --domain=topaz       --theme > ./themes/topaz/locale/en_US/LC_MESSAGES/topaz.pot
ruby ./tools/gettext.rb ./themes/umbra/         --domain=umbra       --theme > ./themes/umbra/locale/en_US/LC_MESSAGES/umbra.pot
ruby ./tools/gettext.rb ./admin/                --domain=admin       --theme > ./admin/locale/en_US/LC_MESSAGES/admin.pot
