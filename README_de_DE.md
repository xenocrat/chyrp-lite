[English](README.md), [Deutsch](README_de_DE.md), [Italiano](README_it_IT.md), [한국인](README_ko_KR.md), [Nederlands](README_nl_NL.md), [简体中文](README_zh_CN.md).

## Was kann Chyrp Lite für mich tun?

Chyrp Lite ermöglicht es, mit minimalem Aufwand einen Blog auf Ihrem eigenen Webserver zu hosten. Du kannst ein traditioneller Blog haben, ein Tumbleblog, oder Sie können jede Menge Anpassungen hinfügen und einen Allzweck-Blog Web-Publishing-Plattform mit Blogging-Funktionen an der Seite erstellen. Sie erhalten fünf wunderschöne Blog-Themen und eine benutzerfreundliche Verwaltungskonsole, die vollständig auf einer Vielzahl von Geräten navigierbar ist während Leistungsfähigkeit von responsivem HTML5. Semantisches Markup und umfassende ARIA-Kennzeichnung sorgen dafür, dass Ihr Blog erfolgreich für Besucher zugänglich ist, die unterstützende Technologien nutzen.

Mit einem flexiblen System aus Feathern und Seiten können Sie Ihre Webseite so gestalten, wie Sie es möchten.
Feathern ermöglichen verschiedene Sorten von Blog-Inhalten – Sie können sich auf absolute Textreinheit beschränken, oder Sie erstellen einen Multimedia-Regenbogen. Mit Seiten können Sie Artikel getrennt von Ihrem Blog-Inhalt veröffentlichen – sei es ein einfaches Kolophon oder eine Hierarchie aus mehreren Seiten, mit optional auch einer Homepage die Ihnen zur Verfügung steht und die Besucher sehen würden, wann sie zum ersten Mal auf Ihrer Website ankommen.


## Was sind die wichtigste Funktionen?

#### Core:
* Einfach zu installieren, einfach zu unterhalten, erweiterbar.
* Gebaut mit reaktionsfähigem und zugänglichem W3C-validiertem HTML5.
* Universelle Unterstützung für Nur-Text, Markdown oder HTML-Markup.
* Personalisiere Ihr Blog mit leistungsstarken Erweiterungen.
* Die Themen-Entwicklung ist mit der Twig-Template-Engine einfach.
* Verwalten Sie Benutzer und Besucher mit einem umfassenden Rechtemodell.

#### Feathern:
* Text: Schreibe textliche Blog-Einträge.
* Foto: Lade ein Bild hoch.
* Zitat: Mache ein Zitat.
* Link: Link zu einer anderen Webseite.
* Video: Lade eine Videodatei hoch.
* Audio: Lade eine Audiodatei hoch.
* Uploader: Lade mehrere Dateien zugleich hoch.

#### Modulen:
* Cacher: Lasse Sie Ihre Blog-Seiten cachen, um die Serverlast zu reduzieren.
* Kategorisieren: Geben Sie jedem Ihrer Blogposts eine Kategorie.
* Tags: Wenden Sie mehrere durchsuchbare Tags (Etiketten) auf Ihre Blogposts an.
* Erwähnbar: Registrieren Sie Web-Erwähnungen von Blogs, die auf Ihr Blog verlinken.
* Kommentare: ein umfassendes Kommentarsystem für Ihr Blog.
* Likes: Ermögliche Ihren Besuchern, ihre Wertschätzung zu zeigen.
* Weiterlesen: Auszug aus langen Blogposts im Blogindex.
* Rechte: Legen Sie Namensnennung und Copyright/Links für Ihre Einträge fest.
* Cascade: Ajax-gestütztes unendliches Scrollen für Ihr Blog.
* Lightbox: On-Seite-Bildbetrachter mit Bildschutz.
* Sitemap: Indizieren Sie Ihr Blog für Suchmaschinen.
* MAPTCHA: Verwenden Sie einfache mathematische Aufgaben, um Spam zu verhindern.
* Highlighter: Syntaxhervorhebung für Ihre Codefragmente.
* Einfaches Einbetten: Der einfachste Weg, Inhalte in Ihr Blog einzubetten.
* Beitragsaufrufe: Behalten Sie die Anzahl der Aufrufe für Ihre Blogeinträge bei.
* MathJax: eine JavaScript-Anzeige-Engine für Mathematik.

## Anforderungen

* [PHP 8.1+](https://www.php.net/supported-versions.php) mit standard extensionen (Session, JSON, Ctype, Filter, libxml, SimpleXML)
* [Multibyte String](https://www.php.net/manual/en/book.mbstring.php)
* [PDO](https://www.php.net/manual/en/book.pdo.php)
* [cURL](https://www.php.net/manual/en/book.curl.php)
* MySQL 4.1+
* SQLite 3+
* PostgreSQL 10+

## Installieren

Sie können Chyrp Lite in drei Schritten installieren:

1. Wenn Sie MySQL verwenden, erstelle eine MySQL-Datenbank mit einem Benutzernamen und einem Passwort.
2. Herunterlade dem [latest release](https://github.com/xenocrat/chyrp-lite/releases), entpacke, und lade es hoch zu ihrem Webserver.
3. Führe den Installationsvorgang aus mit [install.php](install.php) in ihrem Webbrowser.

## Upgradung

Sie können Chyrp Lite in sechs Schritten aktualisieren:

1. __Backup Ihre Datenbank, bevor Sie fortfahren!__
2. Lade die neueste Version von Chyrp Lite herunter.
3. Verschiebe Ihren _uploads_ Mappe und _includes/config.json.php_ an einen sicheren Ort.
4. Überschreibe Ihre aktuelle Version mit der neuen.
5. Stelle Ihre _uploads_ Mappe wieder únd _includes/config.json.php_.
6. Führe den Upgrade-Vorgang aus mit [upgrade.php](upgrade.php) in ihrem Webbrowser.

## Security

Nach der Installation sind diese Dateien für Besucher zugänglich:

* _LICENSE.md_
* _README.md_
* _README_de_DE.md_
* _README_it_IT.md_
* _README_ko_KR.md_
* _README_nl_NL.md_
* _README_zh_CN.md_
* _SECURITY.md_
* _install.php_
* _tools/*_
* _upgade.php_

Sie enthalten keine Geheimnisse, möchten sie aber möglicherweise löschen.

## Documentation

Die Chyrp Lite [wiki](https://chyrplite.net/wiki/) hat umfassende Dokumentation
für Benutzer und Entwickler.

## Autoren

Chyrp Lite wurde von folgenden Personen erstellt:

* Lite Developer: Daniel Pimley
* Chyrp Developer: Arian Xhezairi
* Project Founder: Alex Suraci
* Module autoren und andere Beiträger.

## Licenses

Chyrp Lite ist Copyright 2008-2025 Alex Suraci, Arian Xhezairi, Daniel Pimley, und andere Beiträger,
distribuiert unter dem [BSD license](https://raw.githubusercontent.com/xenocrat/chyrp-lite/master/LICENSE.md).
Bitte siehe auch die [licenses](licenses) Mappe für die ganze lizenz-text allem software packages die distribuiert sind mit Chyrp Lite.
