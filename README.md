## What can Chyrp Lite do for me?

With Chyrp Lite you can run your own blog on your own web server with minimal fuss.
Chyrp Lite provides three beautiful blog themes and a friendly administration environment,
all fully navigable on desktop computers and mobile devices, thanks to the power of
responsive HTML5. Semantic markup and comprehensive ARIA labelling ensure your blog will
be accessible to visitors who use assistive technologies.

With Chyrp Lite's system of Feathers and Pages you can make your blog whatever you want
it to be: you can have a traditional blog, a tumbleblog, or add oodles of customisation
and build a web publishing platform with blogging features on the side.

Feathers support different types of content in your blog posts: you can restrict yourself
to absolute textual purity, or you can turn on everything and blog a multimedia rainbow.
Pages allow you to publish articles on your site that are independent of your blog content.

## What are the key features?

#### Core:
* Responsive and accessible, W3C validated HTML5 for all themes.
* Universal support for plain text, Markdown, or raw HTML.
* Average blog page is fewer than 10 requests and under 500 KB.
* Personalise your blog using powerful extensions.
* Theme development is easy with the Twig template engine.
* Comprehensive rights model for managing your users and visitors.

#### Feathers:
* Text: write textual blog entries.
* Photo: upload an image.
* Quote: make a quotation.
* Link: link to another website.
* Video: upload a video file.
* Audio: upload an audio file.
* Uploader: upload multiple files.

#### Modules:
* Cacher: cache your blog pages for reduced server load.
* Categorize: give each of your blog entries a category.
* Tags: apply multiple searchable tags to your blog entries.
* Comments: a comprehensive comments system for your blog.
* Likes: allow your visitors to show their appreciation.
* Read More: excerpt long blog entries on the blog index.
* Rights: set attribution and copyright/left for your entries.
* Cascade: ajax-powered infinite scrolling for your blog.
* Lightbox: on-page image viewer with image protection.
* Sitemap: index your blog for search engines.
* reCAPTCHA/MAPTCHA: prevent spam.
* Highlighter: syntax highlighting for your code snippets.
* SimpleMDE: WYSIWYG-esque editor for Markdown content.

## Requirements

* PHP 5.3.2+
* MySQL:
  - MySQL 4.1+
  - MySQLi or PDO
* SQLite:
  - SQLite 3+
  - PDO

## Installation

You can install Chyrp Lite in four steps:

1. If using MySQL, create a MySQL database with a username and password.
2. Download the [latest release](https://github.com/xenocrat/chyrp-lite/releases), unzip, and upload to your web server.
3. Open your web browser and navigate to where you uploaded Chyrp Lite.
4. Run the installation process by visiting [install.php](install.php) in your web browser.

That's it! Chyrp Lite will be up and running and ready for you to use.

## Upgrading

Chyrp Lite will tell you when a new version is available. You can update in six steps:

1. __Backup your database before proceeding!__
2. Download the latest version of Chyrp Lite.
3. Copy your config file from _includes/config.json.php_ to somewhere safe.
4. Overwrite your current version with the new one, making sure to retain your _uploads_ folder.
5. Restore your config file to _includes_.
6. Run the upgrade process by visiting [upgrade.php](upgrade.php) in your web browser.

## Documentation

The Chyrp Lite [wiki](https://github.com/xenocrat/chyrp-lite/wiki) has comprehensive documentation
for users and developers.

## Authors

Chyrp Lite was created by the following people:

* Lite Developer: Daniel Pimley
* Chyrp Developer: Arian Xhezairi
* Project Founder: Alex Suraci

&hellip;and other contributors.

## Licenses

Chyrp Lite is Copyright 2008-2016 Alex Suraci, Arian Xhezairi, Daniel Pimley, and other contributors,
distributed under the [X11 license](https://raw.githubusercontent.com/xenocrat/chyrp-lite/master/LICENSE.md).
Twig is Copyright 2009-2016 the Twig Team,
distributed under the [new BSD License](https://raw.githubusercontent.com/twigphp/Twig/master/LICENSE).
jQuery is Copyright jQuery Foundation and other contributors,
distributed under the [MIT License](https://raw.githubusercontent.com/jquery/jquery/master/LICENSE.txt).
Parsedown is Copyright 2013 Emanuil Rusev,
distributed under the [MIT License](https://raw.githubusercontent.com/erusev/parsedown/master/LICENSE.txt).
Highlight.js is Copyright 2006 Ivan Sagalaev,
distributed under the [new BSD License](https://raw.githubusercontent.com/isagalaev/highlight.js/master/LICENSE).
SimpleMDE is Copyright 2015 Next Step Webs, Inc.,
distributed under the [MIT License](https://raw.githubusercontent.com/NextStepWebs/simplemde-markdown-editor/master/LICENSE).
Open Sans is Copyright 2010-2011 Google Corporation,
distributed under the [Apache License v2.0](http://www.apache.org/licenses/LICENSE-2.0.txt).
Open Sans is a trademark of Google and may be registered in certain jurisdictions.
Hack is Copyright 2015 Christopher Simpkins,
distributed under the [Hack Open Font License](https://raw.githubusercontent.com/chrissimpkins/Hack/master/LICENSE.md).
PHP-gettext is Copyright 2003-2009 Danilo Segan, Copyright 2005 Nico Kaiser,
distributed under the [GNU General Public License v2.0](https://gnu.org/licenses/old-licenses/gpl-2.0.txt).
The Incutio XML-RPC Library is Copyright 2010 Simon Willison,
distributed under the [new BSD License](https://raw.githubusercontent.com/xenocrat/chyrp-lite/master/licenses/IXR/LICENSE.txt).
php5-akismet is Copyright Alex Potsides,
distributed under the [BSD License](http://www.opensource.org/licenses/bsd-license.php).
