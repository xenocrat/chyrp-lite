[English](README.md), [Deutsch](README_de_DE.md), [Italiano](README_it_IT.md), [한국인](README_ko_KR.md), [Nederlands](README_nl_NL.md), [简体中文](README_zh_CN.md).

## Wat kan Chyrp Lite voor me doen?

Chyrp Lite maakt het mogelijk een blog te hosten op je eigen webserver met zo weinig mogelijk gedoe. Je kunt een traditionele blog bijhouden of een Tumbleblog of je kunt diverse ingebouwde aanpassingen doen en een breed platform bouwen met blogfunctionaliteiten als aanvulling. Je krijgt 5 mooie themas en een gebruikersvriendelijke administratie-sectie, allemaal zeer goed te grbuiken op een scala van apparaten, dankzij de kracht van responsieve HTML5. Semantische code en uitgebreide ARIA toewijzingen, zorgen ervoor dat je blog ook zeer goed toegankelijk is voor mensen die hulptechnologieën gebruiken.

Met een flexibel system van feathers en paginas, kun je je website precies zó maken zoals je hem hebben wilt. Feathers maken verschillende types van blog-inhoud mogelijk - je kunt oom je blog minimalistisch en puur inhoudelijk houden, of je maakt er een multimedia-spektakel van. Paginas staan je toe om artikelen te schrijven naast je reguliere blogposts - dat kunnen eenvoudige naast elkaar staande paginas zijn of een hierarchische veelvoudige paginastructuur, optioneel inclusief een Homepagina die je bezoekers te zien krijgen wanneer ze voor het eerst op je website aanlanden.


## Wat zijn de belanrijkste kenmerken?

#### Centrale programma:
* Eenvoudig te installeren, makkelijk te onderhouden, uitbreidbaar vanuit het ontwerp.
* Gebouwd met responsieve en toegankelijke W3C HTML5 broncode.
* Universele ondersteuning voor platte tekst, Markdown, of HTML-markup.
* Personaliseer je blog met behulp van krachtige extensies.
* Thema ontwikkeling is eenvoudig met de Twig template-motor.
* Beheer gebruikers en bezoekers met een uitgebreid rechten-model.

#### Feathers:
* Tekst: schrijf tekstuele blogposts.
* Foto: upload een afbeelding.
* Quote: maak een quotatie.
* Link: link naar een andere website.
* Video: upload een video bestand.
* Audio: upload een audio bestand.
* Uploader: upload meerdere bestanden tegelijk.

#### Modules:
* Cacher: cache je blogs voor een lagere serverbelasting.
* Categoriseer: wijs je blogposts toe aan een categorie.
* Tags: gebruik meerdere zoektags voor je blogposts.
* Benoembaar: registreer webmentions van blogs die linken naar de jouwe.
* Reacties: een uitgebreid reactiesysteem voor je blog.
* Vind-ik-leuks: sta je bezoekers toe om hun waardering te tonen.
* Lees Meer: breek lange blogposts af op je voorpagina.
* Rechten: bepaal toewijzing en kopijrechten voor je creaties.
* Cascade: ajax-powered oneindig scrollen voor je blog.
* Lightbox: in-pagina afbeeldingsviewer met beschermingsoptie.
* Sitemap: indexeer je blog voor zoekmachines.
* MAPTCHA: gebruik eenvoudige rekensommen om spam te voorkomen.
* Markeerder: syntax markering voor je code-knipsels.
* Eenvoudig insluiten: de eenvoudigste manier om videos in je blog in te sluiten.
* Post Views: een teller die de aantallen keren dat je blogposts gelezen zijn weergeeft.
* MathJax: een JavaScript weergave-machine voor Wiskundige inhoud.

## Benodigdheden

* [PHP 8.1+](https://www.php.net/supported-versions.php) met standaard extensies (Session, JSON, Ctype, Filter, libxml, SimpleXML)
* [Multibyte String](https://www.php.net/manual/en/book.mbstring.php)
* [PDO](https://www.php.net/manual/en/book.pdo.php)
* [cURL](https://www.php.net/manual/en/book.curl.php)
* MySQL 5.7+
* SQLite 3+
* PostgreSQL 10+

## Installatie

Je kunt Chyrp Lite in drie stappen installeren:

1. Wanneer je MySQL gebruikt, maak dan een database aan met gebruiker en wachtwoord.
2. Download de [laatste versie](https://github.com/xenocrat/chyrp-lite/releases), decomprimeer het bestand, en upload alles naar je webserver.
3. Doorloop het installatieproces door [install.php](install.php) te bezoeken in je webbrowser.

## Upgraden

Je kunt Chyrp Lite in 6 stappen upgraden:

1. __Backup je database voordat je begint!__
2. Download de laatste versie van Chyrp Lite.
3. Verplaats je _uploads_ map en _includes/config.json.php_ naar een veilige plek.
4. Overschrijf je huidige versie met de nieuwe.
5. Herstel je _uploads_ map en _includes/config.json.php_.
6. Doorloop het upgrade-proces door [upgrade.php](upgrade.php) in je webbrowser te bezoeken.

## Security

Na installatie zijn deze bestanden toegankelijk voor bezoekers:

* _LICENSE.md_
* _README.md_
* _README_de_DE.md_
* _README_it_IT.md_
* _README_ko_KR.md_
* _README_nl_NL.md_
* _README_zh_CN.md_
* _SECURITY.md_
* _install.php_
* _includes/caddyfile.conf_
* _includes/htaccess.conf_
* _includes/nginx.conf_
* _includes/cacert.pem_
* _tools/*_
* _upgade.php_

Er staan geen geheimen in, maar u wilt ze misschien verwijderen.

## Documentatie

De Chyrp Lite [wiki](https://chyrplite.net/wiki/) heeft uitgebreide documentatie voor gebruikers en ontwikkelaars.

## Makers

Chyrp Lite was gebouwd door de volgende mensen:

* Lite Ontwikkelaar: Daniel Pimley
* Chyrp Ontwikkelaar: Arian Xhezairi
* Project Oprichter: Alex Suraci
* Diverse moduleschrijvers en bijdragers.

## Licenties

Chyrp Lite is Copyright 2008-2024 Alex Suraci, Arian Xhezairi, Daniel Pimley, en andere bijdragers,
gedistribueerd onder de [BSD licentie](https://raw.githubusercontent.com/xenocrat/chyrp-lite/master/LICENSE.md).
Zie alsjeblieft de [licentie](licenses) map voor de volledige tekst betrekking hebbende op alle software packages gedistribueerd met Chyrp Lite.
