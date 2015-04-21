## What is Chyrp Lite?
Chyrp Lite is an ultra-lightweight blogging engine, written in PHP.

## What can Chyrp Lite do for me?
With Chyrp Lite you can run your own blog on your own web server with minimal fuss. Chyrp Lite provides three beautiful blog themes and a friendly administration environment, all fully navigable on desktop computers and mobile devices, thanks to the power of responsive HTML5. Semantic markup and comprehensive ARIA labelling ensure your blog will be accessible to visitors who use assistive technologies.

Chyrp Lite is powered by a unique Feathers system that allows you to make your blog whatever you want it to be: you can have a traditional blog, a tumbleblog, or add oodles of customisation and build a fully-featured web publishing platform. With the addition of the bundled Homepage module you can use Chyrp Lite as the CMS for a static website, with blogging features on the side.

Specifically, Chyrp Lite offers the following "out of the box" features:

#### Core:
* Responsive and accessible HTML5 for all blog and admin pages.
* Universal support for plain text, Markdown, Textile, or raw HTML.
* One-click markup/markdown previews for posts and pages.
* Send and receive trackbacks and pingbacks on your blog posts.
* Personalise your blog using powerful extensions.
* Semantic markup makes it easy to customise themes or write your own.
* A comprehensive rights model for managing your users and visitors.
* Fully internationalized, with `getttext` localization support.

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
* Homepage: replace the blog index with a homepage.
* Highlighter: syntax highlighting courtesy of [highlight.js](https://highlightjs.org/).

## Requirements
These are the earliest versions of the services with which Chyrp Lite has been tested:

* PHP 5.3.2+
* MySQL:
  - MySQL 4.1+
* SQLite:
  - SQLite 3+
  - PDO

## Installation
You can install Chyrp Lite in four steps:

1. If using MySQL, create a MySQL database with a username and password.
2. Download the [latest release](https://github.com/xenocrat/chyrp-lite/releases), unzip, and upload to your web server.
3. Open your web browser and navigate to where you uploaded Chyrp Lite.
4. Follow through the installer at [install.php](install.php).

That's it! Chyrp Lite will be up and running and ready for you to use.

## Upgrading
Chyrp Lite will tell you when a new version is available. You can update in eight steps:

1. Download the latest version of Chyrp Lite.
2. Copy your config file from `/includes/config.json.php` to somewhere safe.
3. Disable any thrid-party Modules/Feathers that are installed.
4. Overwrite your current Chyrp installation files with the new ones, making sure to retain your `/uploads/` folder.
5. Restore your config file to `/includes/`.
6. Run the upgrade process by navigating to [upgrade.php](upgrade.php).
7. Re-enable your Modules/Feathers.
8. Run the upgrade process again to run any Module/Feather upgrade tasks.

## Documentation
The Chyrp Lite [wiki](https://github.com/xenocrat/chyrp-lite/wiki) has comprehensive documentation for users and developers.
