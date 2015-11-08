#!/bin/sh
ruby ./tools/gettext.rb ./                                            > ./includes/locale/en_US.pot

ruby ./tools/gettext.rb ./feathers/audio/       --domain=audio        > ./feathers/audio/locale/en_US.pot
ruby ./tools/gettext.rb ./feathers/link/        --domain=link         > ./feathers/link/locale/en_US.pot
ruby ./tools/gettext.rb ./feathers/photo/       --domain=photo        > ./feathers/photo/locale/en_US.pot
ruby ./tools/gettext.rb ./feathers/quote/       --domain=quote        > ./feathers/quote/locale/en_US.pot
ruby ./tools/gettext.rb ./feathers/text/        --domain=text         > ./feathers/text/locale/en_US.pot
ruby ./tools/gettext.rb ./feathers/video/       --domain=video        > ./feathers/video/locale/en_US.pot
ruby ./tools/gettext.rb ./feathers/uploader/    --domain=uploader     > ./feathers/uploader/locale/en_US.pot

ruby ./tools/gettext.rb ./modules/cacher/       --domain=cacher       > ./modules/cacher/locale/en_US.pot
ruby ./tools/gettext.rb ./modules/cascade/      --domain=cascade      > ./modules/cascade/locale/en_US.pot
ruby ./tools/gettext.rb ./modules/categorize/   --domain=categorize   > ./modules/categorize/locale/en_US.pot
ruby ./tools/gettext.rb ./modules/comments/     --domain=comments     > ./modules/comments/locale/en_US.pot
ruby ./tools/gettext.rb ./modules/highlighter/  --domain=highlighter  > ./modules/highlighter/locale/en_US.pot
ruby ./tools/gettext.rb ./modules/importers/    --domain=importers    > ./modules/importers/locale/en_US.pot
ruby ./tools/gettext.rb ./modules/lightbox/     --domain=lightbox     > ./modules/lightbox/locale/en_US.pot
ruby ./tools/gettext.rb ./modules/likes/        --domain=likes        > ./modules/likes/locale/en_US.pot
ruby ./tools/gettext.rb ./modules/mail_to_file/ --domain=mail_to_file > ./modules/mail_to_file/locale/en_US.pot
ruby ./tools/gettext.rb ./modules/maptcha/      --domain=maptcha      > ./modules/maptcha/locale/en_US.pot
ruby ./tools/gettext.rb ./modules/read_more/    --domain=read_more    > ./modules/read_more/locale/en_US.pot
ruby ./tools/gettext.rb ./modules/recaptcha/    --domain=recaptcha    > ./modules/recaptcha/locale/en_US.pot
ruby ./tools/gettext.rb ./modules/rights/       --domain=rights       > ./modules/rights/locale/en_US.pot
ruby ./tools/gettext.rb ./modules/sitemap/      --domain=sitemap      > ./modules/sitemap/locale/en_US.pot
ruby ./tools/gettext.rb ./modules/tags/         --domain=tags         > ./modules/tags/locale/en_US.pot

ruby ./tools/gettext.rb ./themes/blossom/       --domain=theme        > ./themes/blossom/locale/en_US.pot
ruby ./tools/gettext.rb ./themes/sparrow/       --domain=theme        > ./themes/sparrow/locale/en_US.pot
ruby ./tools/gettext.rb ./themes/topaz/         --domain=theme        > ./themes/topaz/locale/en_US.pot
ruby ./tools/gettext.rb ./admin/                --domain=theme        > ./admin/locale/en_US.pot
