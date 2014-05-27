##What is Chyrp Lite?

Chyrp Lite is an ultra-lightweight fork of the Chyrp blogging engine. Chyrp Lite is a personal project tailored to my specific needs, but you are very welcome to use it too! If you are looking for more features than you see here, you may want to consider [Chyrp](http://chyrp.net/) instead.

## What can Chyrp Lite do for me?
With Chyrp Lite you can run your own blog on your own web server with minimal fuss. You can have a traditional blog, a tumbleblog, or add oodles of customisation and build a fully-featured web publishing platform. Chyrp Lite provides a beautiful unobtrusive blog theme and a friendly administration environment – both fully compatible with desktop computers and mobile devices, thanks to the power of responsive HTML5.

Chyrp Lite is compatible with Chyrp. If you have a Chyrp blog, you can switch to Chyrp Lite or vice versa!

## How is Chyrp Lite different from Chyrp?
Chyrp Lite is intended to minimise complexity and client-side footprint for users, and to maximise reliability and ease of maintenance for administrators. Modifications to the engine and extensions strive for clean separation of content, style, and behaviour. Wherever possible, [Block, Element, Modifier](http://api.yandex.com/bem/) methodology has been enforced. JavaScripts deemed unduly complex or maintenance-heavy have been removed, as have modules and feathers deemed non-essential.

Specifically, Chyrp Lite deviates from Chyrp as follows:

#### Removed components:
* RedactorJS, flexNav, jQueryUI.
* Firecrest HTML4 blog theme.
* Bookmarklet.
* Post previews.
* Feathers: chat, file.
* Modules: agregator, Dropbox, emailblog, extension manager, paging, smartypants, submission.

#### Modifications:
* Audio feather – native player only, with broad format support for upload.
* Video feather – native player only, upload only with broad format support.
* File feather – replaced with multiple-file Uploader feather.
* Recaptcha library relocated to Recaptcha module.
* Markdown Extra module updated to latest version (1.4.1 at this time).
* Textile module updated to latest version (3.5.5 at this time).
* Pure CSS-styled ajax loader, no default widgets.
* Admin theme-specific components properly separated from core admin functions.
* Various code cleanups and bug fixes.
* &lt;br&gt; elements and utility classes banished from themes, feathers, modules.

#### Added features:
* Refurbished admin theme: responsive HTML5.
* Default HTML5 responsive theme: "Blossom".
* Modules:
  - Lightbox (image viewer).
  - Sitemap (index your blog for search engines).
  - Rights (set attribution and content licence per post).

## Requirements
Chyrp will thrive on virtually any server setup, but we guarantee Chyrp to run on no less than:

* PHP 5 >= 5.3.0
* MySQL:
  - MySQL 4.1+
* SQLite:
  - SQLite 3+
  - PDO

These requirements are more of guidelines, as these are the earliest versions of the services that we have tested Chyrp on. If you are successfully running Chyrp on an earlier version of these services, let us know.

## Installation
You can install Chyrp Lite in four steps:

1. If using MySQL, create a MySQL database with a username and password.
2. Download, unzip, and upload.
3. Open your web browser and navigate to where you uploaded Chyrp.
4. Follow through the installer at [index.php](index.php).

That's it! Chyrp Lite will be up and running and ready for you to use.

## Upgrading
Keeping Chyrp Lite up to date is important to ensure your blog is as safe and as awesome as possible.

1. Download the latest version of Chyrp Lite.
2. Copy your config file from `/includes/config.yaml.php` to somewhere safe.
3. Disable any Modules/Feathers that you downloaded for the release you're upgrading from.
4. Overwrite your current Chyrp installation files with the new ones.
5. Restore your config file to /includes/.
6. Upgrade by navigating to [upgrade.php](upgrade.php).
7. Re-enable your Modules/Feathers.
8. Run the upgrader again. It will run the Module/Feather upgrade tasks.
