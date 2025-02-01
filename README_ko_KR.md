[English](README.md), [Deutsch](README_de_DE.md), [Italiano](README_it_IT.md), [한국인](README_ko_KR.md), [Nederlands](README_nl_NL.md), [简体中文](README_zh_CN.md).

## Chyrp Lite는 나를 위해 무엇을 할 수 있습니까?

Chyrp Lite를 사용하면 번거로움을 최소화하면서 자신의 웹 서버에서 블로그를 호스팅할 수 있습니다.
전통적인 블로그, 텀블 블로그를 가질 수도 있고, 다양한 사용자 정의를 추가하고 측면에 블로깅 기능이
있는 범용 웹 게시 플랫폼을 구축할 수도 있습니다. 5개의 아름다운 블로그 테마와 친근한 관리 콘솔을
사용할 수 있으며 반응이 빠른 HTML5 덕분에 다양한 장치에서 모두 완벽하게 탐색할 수 있습니다.
시맨틱 마크업 및 포괄적인 ARIA 레이블 지정으로 보조 기술을 사용하는 방문자가 블로그에
액세스할 수 있습니다.

깃털 및 페이지의 유연한 시스템을 사용하면 원하는 대로 웹사이트를 만들 수 있습니다.
깃털은 다양한 유형의 블로그 콘텐츠를 가능하게 합니다 - 절대적인 텍스트 순도로 자신을 제한하거나
멀티미디어 모음을 만들 수 있습니다. 페이지를 사용하면 블로그 콘텐츠와 별도로 글을 게시할 수
있습니다 - 단순한 콜로폰이든 여러 페이지의 계층 구조이든, 선택적으로 방문자가 웹 사이트에 처음
도착했을 때 보게 될 홈페이지를 포함할 수 있습니다.

## 주요 기능은 무엇입니까?

#### 핵심:

* 설치가 쉽고 유지 관리가 간단하며 설계상 확장 가능합니다.
* 반응이 빠르고 액세스 가능한 W3C 인증 HTML5로 제작되었습니다.
* 일반 텍스트, Markdown 또는 원시 마크업을 사용 가능합니다.
* 강력한 확장 기능을 사용하여 블로그를 개인화하십시오.
* Twig 템플릿 엔진으로 간편한 테마 개발을 지원합니다.
* 포괄적인 권한 모델로 사용자와 방문자를 관리합니다.

#### 깃털:

* 텍스트: 텍스트 블로그 항목을 작성합니다.
* 사진: 이미지를 업로드합니다.
* 견적: 견적을 작성합니다.
* 링크: 다른 웹사이트로 연결됩니다.
* 동영상: 동영상 파일을 업로드합니다.
* 오디오: 오디오 파일을 업로드합니다.
* 업로더: 여러 파일을 업로드합니다.

#### 모듈:

* Cacher: 서버 부하를 줄이기 위해 블로그 페이지를 캐싱합니다.
* Categorize: 각 블로그 항목에 범주를 지정합니다.
* Tags: 블로그 항목에 검색 가능한 여러 태그를 적용합니다.
* Mentionable: 귀하의 블로그로 연결되는 블로그의 웹멘션을 등록합니다.
* Comments: 블로그를 위한 포괄적인 댓글 시스템입니다.
* Likes: 방문자가 감사를 표시할 수 있습니다.
* Read More: 블로그 색인에서 긴 블로그 항목을 발췌합니다.
* Rights: 출품작에 대한 속성 및 Copyright/left를 설정합니다.
* Cascade: 블로그를 위한 ajax 기반 무한 스크롤.
* Lightbox: 이미지 보호 기능이 있는 온페이지 이미지 뷰어.
* Sitemap: 검색 엔진용 블로그 색인을 생성합니다.
* MAPTCHA: 간단한 수학 문제를 사용하여 스팸을 방지합니다.
* Highlighter: 코드 조각에 대한 구문 강조 표시.
* Easy Embed: 블로그에 콘텐츠를 삽입하는 가장 쉬운 방법입니다.
* Post Views: 블로그 항목의 조회수를 유지합니다.
* MathJax: 수학용 JavaScript 디스플레이 엔진.

## 요구 사항

* 기본 확장 기능이 존재하는 [PHP 8.1+](https://www.php.net/supported-versions.php) (Session, JSON, Ctype, Filter, libxml, SimpleXML)
* [Multibyte String](https://www.php.net/manual/en/book.mbstring.php)
* [PDO](https://www.php.net/manual/en/book.pdo.php)
* [cURL](https://www.php.net/manual/en/book.curl.php)
* MySQL 5.7+
* SQLite 3+
* PostgreSQL 10+

## 설치

세 단계로 Chyrp Lite를 설치할 수 있습니다.

1. MySQL을 사용하는 경우 사용자 이름과 비밀번호로 MySQL 데이터베이스를 생성합니다.
2. [최신 버전](https://github.com/xenocrat/chyrp-lite/releases)을 웹 서버에 업로드하세요.
3. 웹 브라우저에서 [install.php](install.php)를 방문하여 설치 과정을 실행합니다.

## 업그레이드

6단계로 Chyrp Lite를 업그레이드할 수 있습니다.

1. __계속하기 전에 데이터베이스를 백업하세요!__
2. [최신 버전](https://github.com/xenocrat/chyrp-lite/releases)의 Chyrp Lite를 다운로드하십시오.
3. _uploads_ 폴더와 include/config.json.php를 안전한 곳으로 옮기십시오.
4. 기존 버전을 새 버전으로 덮어씁니다.
5. _uploads_ 폴더와 includes/config.json.php 를 복원합니다.
6. 웹 브라우저에서 upgrade.php를 방문하여 업그레이드 프로세스를 실행하세요.

## Security

설치 후 방문자가 다음 파일에 접근할 수 있습니다.

* _LICENSE.md_
* _README.md_
* _README_de_DE.md_
* _README_it_IT.md_
* _README_ko_KR.md_
* _README_nl_NL.md_
* _README_zh_CN.md_
* _SECURITY.md_
* _install.php_
* _themes/&hellip;/*.twig_
* _tools/*_
* _upgade.php_

여기에는 비밀이 없지만, 접근을 제한하는 것이 좋습니다.

## 문서

Chyrp Lite [wiki](https://chyrplite.net/wiki/)에는 사용자와 개발자를 위한
포괄적인 문서가 있습니다.

## 저자

Chyrp Lite는 다음 사람들이 만들었습니다.

* 라이트 개발자: Daniel Pimley
* Chyrp 개발자: Arian Xhezairi
* 프로젝트 설립자: Alex Suraci
* 모듈 작성자 및 기타 기여자.

## 라이선스

Chyrp Lite는 저작권 2008-2025 Alex Suraci, Arian Xhezairi, Daniel Pimley 및 기타 기여자에게
으며 BSD 라이선스 에 따라 배포됩니다 . Chyrp Lite와 함께 배포되는 모든 소프트웨어 패키지의
전체 라이센스 텍스트는 라이센스 디렉토리를 참조하십시오.
