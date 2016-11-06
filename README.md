## What can Chyrp Lite do for me?

Chyrp Lite makes it possible to host a blog on your own web server with minimal fuss.
You can make your website whatever you want it to be: you can have a traditional blog,
a tumbleblog, or you can add oodles of customisation and build a web publishing platform
with blogging features on the side.

Chyrp Lite uses a system of Feathers and Pages. Feathers enable different types of blog
content – you can restrict yourself to absolute textual purity, or you can turn on everything
and blog a multimedia rainbow. Pages let you publish articles separate from your blog content –
from a simple colophon to a multi-page extravaganza.

Chyrp Lite provides three beautiful blog themes and a friendly administration console,
all fully navigable on a broad range of devices, thanks to the power of responsive HTML5.
Semantic markup and comprehensive ARIA labelling ensure your blog will be accessible to
visitors who use assistive technologies.

## What are the key features?

#### Core:
* Easy to install, simple to maintain, extensible by design.
* Built with responsive and accessible W3C-validated HTML5.
* Universal support for plain text, Markdown, or raw markup.
* Personalise your blog using powerful extensions.
* Theme development is easy with the Twig template engine.
* Manage users and visitors with a comprehensive rights model.

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
* Pingable: register pingbacks from blogs that link to yours.
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

You can install Chyrp Lite in three steps:

1. If using MySQL, create a MySQL database with a username and password.
2. Download the [latest release](https://github.com/xenocrat/chyrp-lite/releases), unzip, and upload to your web server.
3. Run the installation process by visiting [install.php](install.php) in your web browser.

## Upgrading

You can upgrade Chyrp Lite in six steps:

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
* Module authors and other contributors.

## Licenses

Chyrp Lite is Copyright 2008-2016 Alex Suraci, Arian Xhezairi, Daniel Pimley, and other contributors,
distributed under the [X11 license](https://raw.githubusercontent.com/xenocrat/chyrp-lite/master/LICENSE.md).
Please see the [licenses](licenses) directory for the full license text of all software packages distributed with Chyrp Lite.
