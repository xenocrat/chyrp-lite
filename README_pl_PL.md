[English](README.md), [Deutsch](README_de_DE.md), [Italiano](README_it_IT.md), [한국인](README_ko_KR.md), [Nederlands](README_nl_NL.md), [简体中文](README_zh_CN.md), [Polski](README_pl_PL.md).

## Co może dla mnie zrobić Chyrp Lite?

Chyrp Lite umożliwia uruchomienie bloga na własnym serwerze WWW przy minimalnym wysiłku. Możesz prowadzić tradycyjnego bloga, tumbleblog lub dodać mnóstwo opcji personalizacji i zbudować ogólną platformę publikacyjną z funkcjami blogowymi jako dodatki. Otrzymujesz sześć estetycznych motywów i przyjazny panel administracyjny, w pełni obsługiwalny na szerokiej gamie urządzeń dzięki responsywnemu HTML5. Semantyczne znaczniki i obszerne etykiety ARIA zapewniają, że Twój blog będzie dostępny dla odwiedzających korzystających z technologii wspomagających.

Dzięki elastycznemu systemowi Feathers i Pages możesz uczynić swoją stronę dokładnie taką, jaką chcesz. Feathers umożliwiają różne typy treści blogowych – możesz ograniczyć się do czystego tekstu lub stworzyć multimedialne, kolorowe wpisy. Pages pozwalają publikować artykuły oddzielone od wpisów blogowych – może to być prosty kolofon lub hierarchia wielu stron, opcjonalnie z stroną główną, którą zobaczą odwiedzający po wejściu na Twoją witrynę.

## Jakie są kluczowe funkcje?

#### Podstawowe:
* Łatwy w instalacji, prosty w utrzymaniu, zaprojektowany z myślą o rozszerzalności.
* Zbudowany przy użyciu responsywnego i dostępnego HTML5 zgodnego ze standardami W3C.
* Pełne wsparcie dla zwykłego tekstu, Markdown lub surowego markupu.
* Personalizuj swojego bloga za pomocą potężnych rozszerzeń.
* Tworzenie motywów jest proste dzięki silnikowi szablonów Twig.
* Zarządzaj użytkownikami i odwiedzającymi dzięki rozbudowanemu modelowi uprawnień.

#### Feathers:
* Tekst: pisz tekstowe wpisy na blogu.
* Zdjęcie: prześlij obraz.
* Cytat: dodaj cytat.
* Link: dodaj odnośnik do innej strony.
* Wideo: prześlij plik wideo.
* Audio: prześlij plik audio.
* Uploader: prześlij wiele plików naraz.

#### Moduły:
* Cacher: buforuje strony bloga, zmniejszając obciążenie serwera.
* Categorize: przypisuj kategorie do wpisów.
* Tags: dodawaj wielokrotne, wyszukiwalne tagi do wpisów.
* Mentionable: rejestruje webmentiony z blogów linkujących do Twojego.
* Comments: rozbudowany system komentarzy.
* Likes: pozwól odwiedzającym wyrażać uznanie.
* Read More: skraca długie wpisy na stronie głównej.
* Rights: ustawiaj przypisania i informacje o prawach autorskich dla wpisów.
* Cascade: przewijanie nieskończone na stronie bloga (AJAX).
* Lightbox: podgląd obrazów na stronie z opcją ochrony obrazów.
* Sitemap: indeksuje bloga dla wyszukiwarek.
* MAPTCHA: proste zadania matematyczne zapobiegające spamowi.
* Highlighter: podświetlanie składni dla fragmentów kodu.
* Easy Embed: najprostszy sposób osadzania treści na blogu.
* Post Views: zlicza wyświetlenia wpisów.
* MathJax: silnik JavaScript do wyświetlania matematyki.
* Inject: używaj triggerów i filtrów do wstrzykiwania treści.

## Wymagania

* [PHP 8.1+](https://www.php.net/supported-versions.php) z domyślnymi rozszerzeniami (Session, JSON, Ctype, Filter, libxml, SimpleXML)
* [Multibyte String](https://www.php.net/manual/en/book.mbstring.php)
* [PDO](https://www.php.net/manual/en/book.pdo.php)
* [cURL](https://www.php.net/manual/en/book.curl.php)
* MySQL 5.7+
* SQLite 3+
* PostgreSQL 10+

## Instalacja

Możesz zainstalować Chyrp Lite w trzech krokach:

1. Jeśli używasz MySQL, utwórz bazę danych MySQL wraz z nazwą użytkownika i hasłem.
2. Pobierz najnowsze wydanie z [releases](https://github.com/xenocrat/chyrp-lite/releases), rozpakuj i wgraj na serwer.
3. Uruchom instalację, odwiedzając w przeglądarce stronę [install.php](install.php).

Jeśli wolisz [Docker](https://www.docker.com/), instalacja przebiega w czterech krokach:

1. Pobierz [najnowsze wydanie](https://github.com/xenocrat/chyrp-lite/releases) i rozpakuj je.
2. Przejrzyj `docker-compose.yaml` i zmodyfikuj w razie potrzeby.
3. Uruchom `docker compose up -d --build`, aby zbudować i uruchomić kontener.
4. Uruchom instalację, odwiedzając w przeglądarce stronę [install.php](install.php).

## Aktualizacja

Możesz zaktualizować Chyrp Lite w sześciu krokach:

1. __Zrób kopię zapasową bazy danych przed przystąpieniem do aktualizacji!__
2. Pobierz najnowszą wersję Chyrp Lite.
3. Przenieś folder _uploads_ i plik _includes/config.json.php_ w bezpieczne miejsce.
4. Nadpisz obecną wersję nowymi plikami.
5. Przywróć folder _uploads_ i plik _includes/config.json.php_.
6. Uruchom proces aktualizacji, odwiedzając w przeglądarce stronę [upgrade.php](upgrade.php).

Jeśli używasz Dockera, możesz zaktualizować w czterech krokach:

1. __Zrób kopię zapasową wolumenów Docker przed przystąpieniem do aktualizacji!__
2. Pobierz najnowszą wersję Chyrp Lite.
3. Skopiuj zmiany, które wprowadziłeś w `docker-compose.yaml`.
4. Uruchom `docker compose up -d --build`.

W przypadku korzystania z Dockera nie ma potrzeby uruchamiania [upgrade.php](upgrade.php).

## Bezpieczeństwo

Po instalacji następujące pliki są dostępne dla odwiedzających:

* _LICENSE.md_
* _README.md_
* _README_de_DE.md_
* _README_it_IT.md_
* _README_ko_KR.md_
* _README_nl_NL.md_
* _README_pl_PL.md_
* _README_zh_CN.md_
* _SECURITY.md_
* _install.php_
* _themes/&hellip;/*.twig_
* _tools/*_
* _upgrade.php_

Nie zawierają one żadnych sekretów, ale możesz rozważyć ograniczenie do nich dostępu.

## Dokumentacja

Wiki Chyrp Lite ma obszerną dokumentację dla użytkowników i deweloperów: https://chyrplite.net/wiki/

## Autorzy

Chyrp Lite został stworzony przez następujące osoby:

* Lite Developer: Daniel Pimley
* Chyrp Developer: Arian Xhezairi
* Project Founder: Alex Suraci
* Autorzy modułów i inni współpracownicy.

## Licencje

Chyrp Lite jest własnością Alex Suraci, Arian Xhezairi, Daniel Pimley oraz innych współautorów (2008–2026) i jest rozpowszechniany na warunkach [licencji BSD](https://raw.githubusercontent.com/xenocrat/chyrp-lite/master/LICENSE.md). Zobacz katalog `licenses` po pełny tekst licencji wszystkich pakietów dołączonych do Chyrp Lite.
