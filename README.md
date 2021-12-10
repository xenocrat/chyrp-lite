[简体中文](README_zh_CN.md), [Italiano](README_it_IT.md).

## What can Chyrp Lite do for me?

Chyrp Lite makes it possible to host a blog on your own web server with minimal fuss. You can have
a traditional blog, a tumbleblog, or you can add oodles of customisation and build a general-purpose
web publishing platform with blogging features on the side.

With a flexible system of Feathers and Pages, you can make your website whatever you want it to be.
Feathers enable different types of blog content – you can restrict yourself to absolute textual purity,
or you can create a multimedia rainbow. Pages let you publish articles separate from your blog content
– be it a simple colophon or a hierarchy of multiple pages, optionally including a homepage that your
visitors will see when they first arrive at your website.

You get four beautiful blog themes and a friendly administration console, all fully navigable on
a broad range of devices, thanks to the power of responsive HTML5. Semantic markup and comprehensive
ARIA labelling ensure your blog will be accessible to visitors who use assistive technologies.
Chyrp Lite also implements a complete WordPress-compatible MetaWeblog XML-RPC API that allows you to
do many blogging tasks remotely without having to visit your blog's website.

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
* MAPTCHA: use simple mathematics problems to prevent spam.
* Highlighter: syntax highlighting for your code snippets.
* Easy Embed: the easiest way to embed videos in your blog.
* Post Views: maintain a view count for your blog entries.
* MathJax: A JavaScript display engine for mathematics.

## Requirements

* [PHP](https://www.php.net/supported-versions.php) with default extensions (Session, JSON, Ctype, libxml, SimpleXML)
* [PDO](https://www.php.net/manual/en/book.pdo.php)
* MySQL 4.1+
* SQLite 3+
* PostgreSQL 10+

## Installation

You can install Chyrp Lite in three steps:

1. If using MySQL, create a MySQL database with a username and password.
2. Download the [latest release](https://github.com/xenocrat/chyrp-lite/releases), unzip, and upload to your web server.
3. Run the installation process by visiting [install.php](install.php) in your web browser.

## Upgrading

You can upgrade Chyrp Lite in six steps:

1. __Backup your database before proceeding!__
2. Download the latest version of Chyrp Lite.
3. Move your _uploads_ folder and _includes/config.json.php_ somewhere safe.
4. Overwrite your current version with the new one.
5. Restore your _uploads_ folder and _includes/config.json.php_.
6. Run the upgrade process by visiting [upgrade.php](upgrade.php) in your web browser.

## Documentation

The Chyrp Lite [wiki](https://chyrplite.net/wiki/) has comprehensive documentation
for users and developers.

## Authors

Chyrp Lite was created by the following people:

* Lite Developer: Daniel Pimley
* Chyrp Developer: Arian Xhezairi
* Project Founder: Alex Suraci
* Module authors and other contributors.

## Licenses

Chyrp Lite is Copyright 2008-2022 Alex Suraci, Arian Xhezairi, Daniel Pimley, and other contributors,
distributed under the [BSD license](https://raw.githubusercontent.com/xenocrat/chyrp-lite/master/LICENSE.md).
Please see the [licenses](licenses) directory for the full license text of all software packages distributed with Chyrp Lite.
