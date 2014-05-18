Chyrp Lite
==========

Chyrp Lite is a fork of the Chyrp blogging engine, modified to be ultra lightweight. This fork is a personal project tailored to my specific needs, but you are free to use it if you think it also meets your needs. If you are looking for more features, you may want to consider [Chyrp](http://chyrp.net/) instead.

Chyrp Lite is intended to minimise complexity and client-side footprint, and to maximise reliability and ease of maintenance. Specifically, Chyrp Lite deviates from Chyrp as follows:

### Removed
* Bookmarklet.
* Post previews.
* RedactorJS.
* Chat Feather.
* jQueryUI (soon).
* flexNav (soon).
* Firecrest theme.
* PNG admin icons.
* Modules:
  - Agregator
  - Dropbox
  - emailblog
  - extension manager
  - paging
  - smartypants
  - submission
  - textilize

### Modified
* Audio Feather – native player only.
* Video Feather – native player only, upload only.
* Recapthca library relocated to module.
* Markdown library updated to 1.4.1.
* Transparent ajax loader, no default widgets.
* Various code cleanups and bug fixes.

### Added
* New HTML5 admin theme (coming soon).
* Default HTML5 responsive theme: Blossom.
* Modules:
  - Lightbox
  - Sitemap
  - Rights

Requirements
============
Chyrp will thrive on virtually any server setup, but we guarantee Chyrp to run on no less than:

* PHP 5 >= 5.3.0
* MySQL:
  - MySQL 4.1+
* SQLite:
  - SQLite 3+
  - PDO

These requirements are more of guidelines, as these are the earliest versions of the services that we have tested Chyrp on. If you are successfully running Chyrp on an earlier version of these services, let us know.

Installation
============
Installing Chyrp is easier than you expect. You can do it in four steps:

1. If using MySQL, create a MySQL database with a username and password.
2. Download, unzip, and upload.
3. Open your web browser and navigate to where you uploaded Chyrp.
4. Follow through the installer at [index.php](index.php).

That's it! Chyrp will be up and running and ready for you to use.

Upgrading
=========
Keeping Chyrp up to date is important to make sure that your blog is as safe and as awesome as possible.

1. Download the latest version of Chyrp.
2. Copy your config file from `/includes/config.yaml.php` to somewhere safe.
3. Disable any Modules/Feathers that you downloaded for the release you're upgrading from.
4. Overwrite your current Chyrp installation files with the new ones.
5. Restore your config files<sup>1</sup> back to /includes/.
6. Upgrade by navigating to [upgrade.php](upgrade.php), and restore any backups.
7. Re-enable your Modules/Feathers.
8. Run the upgrader again. It will run the Module/Feather upgrade tasks.
