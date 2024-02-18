<?php
/**
 * @copyright Copyright (c) 2014 Carsten Brandt
 * @license https://github.com/cebe/markdown/blob/master/LICENSE
 * @link https://github.com/cebe/markdown#readme
 */

namespace cebe\markdown\block;

/**
 * Adds inline and block HTML support
 */
trait HtmlTrait
{
	/**
	 * @var array HTML elements considered as inline elements.
	 * @see https://spec.commonmark.org/0.31.2/#html-blocks
	 */
	protected $type6HtmlElements = [
		'address', 'article', 'aside',
		'base', 'basefont', 'blockquote', 'body',
		'caption', 'center', 'col', 'colgroup',
		'dd', 'details', 'dialog', 'dir', 'div', 'dl', 'dt',
		'fieldset', 'figcaption', 'figure', 'footer', 'form', 'frame', 'frameset',
		'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'head', 'header', 'hr', 'html',
		'iframe',
		'legend', 'li', 'link',
		'main', 'menu', 'menuitem',
		'nav', 'noframes', 
		'ol', 'optgroup', 'option',
		'p', 'param',
		'section', 'source', 'summary',
		'table', 'tbody', 'td', 'tfoot', 'th', 'thead', 'title', 'tr', 'track',
		'ul',
	];

	/**
	 * identify a line as the beginning of a HTML block.
	 */
	protected function identifyHtml($line, $lines, $current)
	{
		if ($line[0] !== '<' || isset($line[1]) && $line[1] == ' ') {
			return false; // no html tag
		}
		if (strncmp($line, '<script', 7) === 0) {
			return true; // type 1: script block
		}
		if (strncmp($line, '<pre', 4) === 0) {
			return true; // type 1: pre block
		}
		if (strncmp($line, '<style', 6) === 0) {
			return true; // type 1: style block
		}
		if (strncmp($line, '<!--', 4) === 0) {
			return true; // type 2: html comment
		}
		if (strncmp($line, '<?', 2) === 0) {
			return true; // type 3: pre-processor
		}
		if (strncmp($line, '<!', 2) === 0 && ctype_alpha(substr($line, 2, 1))) {
			return true; // type 4: declaration
		}
		if (strncmp($line, '<![CDATA[', 9) === 0) {
			return true; // type 5: cdata
		}
		$patterns = implode("|", $this->type6HtmlElements);
		if (preg_match("/^<\/?($patterns)(\s|>|\/>|$)/i", $line)) {
			return true; // type 6
		}
		if (preg_match("/^<\/?[a-z][^>]*>(\s)*$/i", $line)) {
			return true; // type 7
		}
		return false;
	}

	/**
	 * Consume lines for an HTML block
	 */
	protected function consumeHtml($lines, $current)
	{
		$content = [];
		if (strncmp($lines[$current], '<script', 7) === 0) {
			// type 1: script
			for ($i = $current, $count = count($lines); $i < $count; $i++) {
				$line = $lines[$i];
				$content[] = $line;
				if (strpos($line, '</script>') !== false) {
					break;
				}
			}
		} elseif (strncmp($lines[$current], '<pre', 4) === 0) {
			// type 1: style
			for ($i = $current, $count = count($lines); $i < $count; $i++) {
				$line = $lines[$i];
				$content[] = $line;
				if (strpos($line, '</pre>') !== false) {
					break;
				}
			}
		} elseif (strncmp($lines[$current], '<style', 6) === 0) {
			// type 1: style
			for ($i = $current, $count = count($lines); $i < $count; $i++) {
				$line = $lines[$i];
				$content[] = $line;
				if (strpos($line, '</style>') !== false) {
					break;
				}
			}
		} elseif (strncmp($lines[$current], '<!--', 4) === 0) {
			// type 2: html comment
			for ($i = $current, $count = count($lines); $i < $count; $i++) {
				$line = $lines[$i];
				$content[] = $line;
				if (strpos($line, '-->') !== false) {
					break;
				}
			}
		} elseif (strncmp($lines[$current], '<?', 2) === 0) {
			// type 3: pre-processor
			for ($i = $current, $count = count($lines); $i < $count; $i++) {
				$line = $lines[$i];
				$content[] = $line;
				if (strpos($line, '?>') !== false) {
					break;
				}
			}
		} elseif (strncmp($lines[$current], '<!', 2) === 0) {
			// type 4: declaration
			for ($i = $current, $count = count($lines); $i < $count; $i++) {
				$line = $lines[$i];
				$content[] = $line;
				if (strpos($line, '>') !== false) {
					break;
				}
			}
		} elseif (strncmp($lines[$current], '<![CDATA[', 9) === 0) {
			// type 5: cdata
			for ($i = $current, $count = count($lines); $i < $count; $i++) {
				$line = $lines[$i];
				$content[] = $line;
				if (strpos($line, ']]>') !== false) {
					break;
				}
			}
		} else {
			// type 6 or 7 tag - consume until newline
			$content = [];
			for ($i = $current, $count = count($lines); $i < $count; $i++) {
				$line = $lines[$i];
				if (ltrim($line) !== '') {
					$content[] = $line;
				} else {
					break;
				}
			}
		}
		$block = [
			'html',
			'content' => implode("\n", $content),
		];
		return [$block, $i];
	}

	/**
	 * Renders an HTML block
	 */
	protected function renderHtml($block)
	{
		return $block['content'] . "\n";
	}

	protected function parseEntityMarkers()
	{
		return array('&');
	}

	/**
	 * Parses an & or a html entity definition.
	 * @marker &
	 */
	protected function parseEntity($text)
	{
		// html entities e.g. &copy; &#169; &#x00A9;
		if (preg_match('/^&#?[\w\d]+;/', $text, $matches)) {
			return [['inlineHtml', $matches[0]], strlen($matches[0])];
		} else {
			return [['text', '&amp;'], 1];
		}
	}

	/**
	 * renders a html entity.
	 */
	protected function renderInlineHtml($block)
	{
		return $block[1];
	}

	protected function parseInlineHtmlMarkers()
	{
		return array('<');
	}

	/**
	 * Parses inline HTML.
	 * @marker <
	 */
	protected function parseInlineHtml($text)
	{
		if (strpos($text, '>') !== false) {
			if (preg_match('~^</?(\w+\d?)( .*?)?>~s', $text, $matches)) {
				// HTML tags
				return [['inlineHtml', $matches[0]], strlen($matches[0])];
			} elseif (preg_match('~^<!--.*?-->~s', $text, $matches)) {
				// HTML comments
				return [['inlineHtml', $matches[0]], strlen($matches[0])];
			}
		}
		return [['text', '&lt;'], 1];
	}

	protected function parseGtMarkers()
	{
		return array('>');
	}

	/**
	 * Escapes `>` characters.
	 * @marker >
	 */
	protected function parseGt($text)
	{
		return [['text', '&gt;'], 1];
	}
}
