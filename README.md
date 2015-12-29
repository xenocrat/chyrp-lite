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

## Requirements

* PHP 5.3.2+
* MySQL:
  - MySQL 4.1+
  - MySQLi or PDO
* SQLite:
  - SQLite 3+
  - PDO
* PostgreSQL:
  - PostgreSQL 7.1+
  - PDO

## Installation
You can install Chyrp Lite in four steps:

1. If using MySQL, create a MySQL database with a username and password.
2. Download the [latest release](https://github.com/xenocrat/chyrp-lite/releases), unzip, and upload to your web server.
3. Open your web browser and navigate to where you uploaded Chyrp Lite.
4. Follow through the installer at [install.php](install.php).

That's it! Chyrp Lite will be up and running and ready for you to use.

## Upgrading
Chyrp Lite will tell you when a new version is available. You can update in six steps:

1. __Backup your database before proceeding!__
2. Download the latest version of Chyrp Lite.
3. Copy your config file from `/includes/config.json.php` to somewhere safe.
4. Overwrite your current Chyrp installation files with the new ones, making sure to retain your `/uploads/` folder.
5. Restore your config file to `/includes/`.
6. Run the upgrade process by navigating to [upgrade.php](upgrade.php).

## Documentation
The Chyrp Lite [wiki](https://github.com/xenocrat/chyrp-lite/wiki) has comprehensive documentation
for users and developers.

## Authors

Chyrp Lite was created by the following people:

* Lite Developer: Daniel Pimley
* Chyrp Developer: Arian Xhezairi
* Project Founder: Alex Suraci

## License

Chyrp Lite is Copyright 2015 Alex Suraci, Arian Xhezairi,
Daniel Pimley, and other contributors.

Permission is hereby granted, free of charge, to any person
obtaining a copy of this software and associated documentation
files (the "Software"), to deal in the Software without
restriction, including without limitation the rights to use,
copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the
Software is furnished to do so, subject to the following
conditions:

The above copyright notice and this permission notice shall be
included in all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES
OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT
HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY,
WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR
OTHER DEALINGS IN THE SOFTWARE.

Except as contained in this notice, the name(s) of the above
copyright holders shall not be used in advertising or otherwise
to promote the sale, use or other dealings in this Software
without prior written authorization.

Twig is Copyright 2009-2014 by the Twig Team,
distributed under the [new BSD License](https://raw.githubusercontent.com/twigphp/Twig/1.x/LICENSE).
jQuery is Copyright jQuery Foundation and other contributors,
distributed under the [MIT License](https://raw.githubusercontent.com/jquery/jquery/master/LICENSE.txt).
Parsedown is Copyright 2013 Emanuil Rusev,
distributed under the [MIT License](https://raw.githubusercontent.com/erusev/parsedown/master/LICENSE.txt).
Highlighter.js is Copyright 2006 Ivan Sagalaev,
distributed under the [BSD License](https://raw.githubusercontent.com/isagalaev/highlight.js/master/LICENSE).
Open Sans is Copyright 2010-2011 Google Corporation,
distributed under the [Apache License v2.0](http://www.apache.org/licenses/LICENSE-2.0.txt).
Open Sans is a trademark of Google and may be registered in certain jurisdictions.
Hack is Copyright 2015 Christopher Simpkins,
distributed under the [Hack Open Font License](https://raw.githubusercontent.com/chrissimpkins/Hack/master/LICENSE.md).
PHP-gettext is Copyright 2003-2009 Danilo Segan, Copyright 2005 Nico Kaiser,
distributed under the [GNU General Public License v2.0](https://gnu.org/licenses/old-licenses/gpl-2.0.txt).
The Incutio XML-RPC Library is Copyright 2010 Simon Willison,
distributed under the [new BSD License](http://www.opensource.org/licenses/bsd-license.php).
Akismet.php is Copyright Alex Potsides,
distributed under the [BSD License](http://www.opensource.org/licenses/bsd-license.php BSD License).
