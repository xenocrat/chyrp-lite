[English](README.md), [Deutsch](README_de_DE.md), [Italiano](README_it_IT.md), [한국인](README_ko_KR.md), [Nederlands](README_nl_NL.md), [简体中文](README_zh_CN.md).

## Chyrp Lite能为我做什么？

Chyrp Lite使您可以在自己的Web服务器上轻松托管一个博客。 您可以有创建一个传统的博客，一个tumble博客（像是微博），或者您可以定制和建立一个通用的带有博客功能的网络发布平台。
您可以获得五个漂亮的博客主题和一个友好的管理控制台，归功于响应式HTML5的强大功能，可以在任何设备上使用。语义标记和综合
ARIA标签可确保您的博客可供使用辅助技术的访问者访问。

通过灵活的羽毛和页面系统，您可以在网站上创作任何您想要的任何内容。
羽毛可以使用不同类型的博客内容 - 您可以将自己限制为绝对纯文本，或者你可以创建一个多媒体彩虹。 
通过页面可以发布与您的博客内容分开的文章。
– 无论是简单的内容还是多个页面的层次结构，可以选择访问者会在他们第一次到达您的网站时看到您的主页
   
## 关键特性是什么？

#### 核心:
* 易于安装，易于维护，可通过设计进行扩展。
* 使用响应式和无障碍W3C标准的HTML5构建。
* 普遍支持纯文本，Markdown或原始标记。
* 使用强大的扩展来个性化您的博客。
* 使用Twig模板引擎可以轻松实现主题开发。
* 使用全面的权限模式管理用户和访问者。

#### 羽毛:
* Text: 编写文本博客条目。
* Photo: 上传图片。
* Quote: 创建引用。
* Link: 链接到其他站点。
* Video: 上传视频文件。
* Audio: 上传音频文件。
* Uploader: 上传多个文件。

#### 模块:
* Cacher: 缓存您的博客页面以减少服务器负载。
* Categorize: 为每个博客条目分配一个类别。
* Tags: 将多个可搜索标签应用于您的博客条目。
* Mentionable: 从链接到你的博客注册Webmentions。
* Comments: 一个全面的博客评论系统。
* Likes: 让您的访客表示喜欢。
* Read More: 摘录博客索引中的长博客条目。
* Rights: 设置来源和版权/留下您的作品。
* Cascade: ajax支持无限滚动您的博客。
* Lightbox: 带图像保护的页面图像查看器。
* Sitemap: 在搜索引擎上索引您的博客。
* MAPTCHA: 使用简单的算法来防止垃圾邮件。
* Highlighter: 语法突出显示您的代码片段。
* Easy Embed: 在您的博客中嵌入内容的最简单方法。
* Post Views: 维护您的博客条目的查看次数。
* MathJax: JavaScript的数学显示引擎。

## 安装要求

* [PHP 8.1+](https://www.php.net/supported-versions.php) 具有默认扩展名 (Session, JSON, Ctype, Filter, libxml, SimpleXML)
* [Multibyte String](https://www.php.net/manual/en/book.mbstring.php)
* [PDO](https://www.php.net/manual/en/book.pdo.php)
* [cURL](https://www.php.net/manual/en/book.curl.php)
* MySQL 5.7+
* SQLite 3+
* PostgreSQL 10+

## 安装

您可以分三步安装Chyrp Lite：

1. 如果使用MySQL，使用用户名和密码创建一个MySQL数据库。
2. 下载 [最新发布版](https://github.com/xenocrat/chyrp-lite/releases), 解压并上传至您的web服务器。
3. 在您的浏览其中访问 [install.php](install.php) 来运行安装程序。

## 升级

您可以分六步升级Chyrp Lite：

1. __继续之前备份您的数据库！__
2. 下载最新版本的Chyrp Lite。
3. 将您的 _uploads_ 文件夹和 _includes/config.json.php_ 移动到安全的地方。
4. 使用新版本覆盖当前版本。
5. 恢复您的 _uploads_ 文件夹和 _includes/config.json.php_。
6. 在您的浏览其中访问 [upgrade.php](upgrade.php) 来运行升级程序。

## Security

安装后，访问者可以访问这些文件：

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
* _upgrade.php_

它们不包含任何秘密，但您可能想要限制访问。

## 文档

Chyrp Lite [wiki](https://chyrplite.net/wiki/) 为用户和开发者提供了非常全面的文档。

## 作者

Chyrp Lite由以下人员创建:

* Lite版开发者: Daniel Pimley
* Chyrp开发者: Arian Xhezairi
* 项目创始人: Alex Suraci
* 模块作者和其他贡献者。

## Licenses

Chyrp Lite is Copyright 2008-2025 Alex Suraci, Arian Xhezairi, Daniel Pimley, and other contributors,
distributed under the [BSD license](https://raw.githubusercontent.com/xenocrat/chyrp-lite/master/LICENSE.md).
Please see the [licenses](licenses) directory for the full license text of all software packages distributed with Chyrp Lite.
