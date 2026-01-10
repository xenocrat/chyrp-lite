<?php
/**
 * @copyright Copyright 2014 Carsten Brandt, 2024-2026 Daniel Pimley
 * @license https://github.com/xenocrat/chyrp-markdown/blob/master/LICENSE
 * @link https://github.com/xenocrat/chyrp-markdown#readme
 */

namespace xenocrat\markdown\block;

/**
 * Adds inline and block HTML support.
 */
trait HtmlTrait
{
	/**
	 * Identify a line as the beginning of a HTML block.
	 */
	protected function identifyHtml($line, $lines, $current): bool
	{
		if (
			$line[0] === ' '
			&& strspn($line, ' ') < 4
		) {
		// Trim up to three spaces.
			$line = ltrim($line, ' ');
		}
		if ($line[0] !== '<' || isset($line[1]) && $line[1] == ' ') {
		// No tag.
			return false;
		}
		if (strncasecmp($line, '<script', 7) === 0) {
		// Type 1: script.
			return true;
		}
		if (strncasecmp($line, '<pre', 4) === 0) {
		// Type 1: pre.
			return true;
		}
		if (strncasecmp($line, '<style', 6) === 0) {
		// Type 1: style.
			return true;
		}
		if (strncasecmp($line, '<textarea', 9) === 0) {
		// Type 1: textarea.
			return true;
		}
		if (str_starts_with($line, '<!--')) {
		// Type 2: comment.
			return true;
		}
		if (str_starts_with($line, '<?')) {
		// Type 3: processor.
			return true;
		}
		if (preg_match('/^<![a-z]/i', $line)) {
		// Type 4: declaration.
			return true;
		}
		if (str_starts_with($line, '<![CDATA[')) {
		// Type 5: CDATA.
			return true;
		}
		if (
			preg_match(
				'/^<\/?(
					address|article|aside|
					base|basefont|blockquote|body|
					caption|center|col|colgroup|
					dd|details|dialog|dir|div|dl|dt|
					fieldset|figcaption|figure|footer|form|frame|frameset|
					h1|h2|h3|h4|h5|h6|head|header|hr|html|
					iframe|
					legend|li|link|
					main|menu|menuitem|
					nav|noframes| 
					ol|optgroup|option|
					p|param|
					section|source|summary|
					table|tbody|td|tfoot|th|thead|title|tr|track|
					ul
					)(\s|>|\/>|$)/ix',
				$line
			)
		) {
		// Type 6.
			return true;
		}
		if (
			preg_match(
				'/^<(\/)?[a-z][a-z0-9\-]*
					(?(1)\s*|(?:\s+[a-z_:][a-z0-9_:\.\-]*(?:\s*=\s*
							(?(?=[\"\'])([\"\'])(?:(?!\2).)*\2|[^\s\n"\'=<>`]+)
					)?)*\s*\/?)>\s*$/ix',
				$line,
				$matches
			)
			&& (
				!isset($lines[$current - 1])
				|| $lines[$current - 1] === ''
				|| ltrim($lines[$current - 1]) === ''
			)
		) {
		// Type 7.
			return true;
		}
		return false;
	}

	/**
	 * Consume lines for an HTML block.
	 */
	protected function consumeHtml($lines, $current): array
	{
		$content = [];
		$line = ltrim($lines[$current], ' ');

		if (strncasecmp($line, '<script', 7) === 0) {
		// Type 1: script.
			for ($i = $current, $count = count($lines); $i < $count; $i++) {
				$line = $lines[$i];
				$content[] = $line;
				if (stripos($line, '</script>') !== false) {
					break;
				}
			}
		} elseif (strncasecmp($line, '<pre', 4) === 0) {
		// Type 1: pre.
			for ($i = $current, $count = count($lines); $i < $count; $i++) {
				$line = $lines[$i];
				$content[] = $line;
				if (stripos($line, '</pre>') !== false) {
					break;
				}
			}
		} elseif (strncasecmp($line, '<style', 6) === 0) {
		// Type 1: style.
			for ($i = $current, $count = count($lines); $i < $count; $i++) {
				$line = $lines[$i];
				$content[] = $line;
				if (stripos($line, '</style>') !== false) {
					break;
				}
			}
		} elseif (strncasecmp($line, '<textarea', 9) === 0) {
		// Type 1: textarea.
			for ($i = $current, $count = count($lines); $i < $count; $i++) {
				$line = $lines[$i];
				$content[] = $line;
				if (stripos($line, '</textarea>') !== false) {
					break;
				}
			}
		} elseif (str_starts_with($line, '<!--')) {
		// Type 2: comment.
			for ($i = $current, $count = count($lines); $i < $count; $i++) {
				$line = $lines[$i];
				$content[] = $line;
				if (strpos($line, '-->') !== false) {
					break;
				}
			}
		} elseif (str_starts_with($line, '<?')) {
		// Type 3: processor.
			for ($i = $current, $count = count($lines); $i < $count; $i++) {
				$line = $lines[$i];
				$content[] = $line;
				if (strpos($line, '?>') !== false) {
					break;
				}
			}
		} elseif (str_starts_with($line, '<!')) {
		// Type 4: declaration.
			for ($i = $current, $count = count($lines); $i < $count; $i++) {
				$line = $lines[$i];
				$content[] = $line;
				if (strpos($line, '>') !== false) {
					break;
				}
			}
		} elseif (str_starts_with($line, '<![CDATA[')) {
		// Type 5: CDATA.
			for ($i = $current, $count = count($lines); $i < $count; $i++) {
				$line = $lines[$i];
				$content[] = $line;
				if (strpos($line, ']]>') !== false) {
					break;
				}
			}
		} else {
		// Type 6 or 7 tag - consume until blank line...
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
	 * Renders an HTML block.
	 */
	protected function renderHtml($block): string
	{
		return $block['content'] . "\n";
	}

	protected function parseEntityMarkers(): array
	{
		return array('&');
	}

	/**
	 * Parses an & or a HTML entity definition.
	 *
	 * @marker &
	 */
	protected function parseEntity($text): array
	{
		if (
			preg_match(
				'/^&(\#[\d]{1,7}|\#[x][a-f0-9]{1,6}|[\w\d]{2,});/i',
				$text,
				$matches
			)
		) {
		// HTML entity.
			return [['entity', $matches[0]], strlen($matches[0])];
		} else {
		// Just an ampersand.
			return [['text', '&amp;'], 1];
		}
	}

	/**
	 * Renders a HTML entity definition.
	 */
	protected function renderEntity($block): string
	{
		return $this->escapeHtmlEntities(
			$this->unEscapeHtmlEntities(
				$block[1],
				ENT_QUOTES | ENT_SUBSTITUTE
			),
			ENT_COMPAT | ENT_SUBSTITUTE | ENT_DISALLOWED
		);
	}

	protected function parseLtMarkers(): array
	{
		return array('<');
	}

	/**
	 * Parses inline HTML.
	 *
	 * @marker <
	 */
	protected function parseLt($text): array
	{
		if (strpos($text, '>') !== false) {
			// First try bracketed link if we have LinkTrait.
			if (method_exists($this, 'parseBracketedLink')) {
				$block = $this->parseBracketedLink($text);
				if ($block[0][0] !== 'text') {
					return $block;
				}
			}
			if (
				// Comment.
				preg_match('/^<!--(-?>|.*?-->)/s', $text, $matches)
				// Processor.
				|| preg_match('/^<\?.*?\?>/s', $text, $matches)
				// Declaration.
				|| preg_match('/^<![a-z].*?>/is', $text, $matches)
				// CDATA.
				|| preg_match('/^<!\[CDATA\[.*?\]\]>/s', $text, $matches)
			) {
				return [['lt', $matches[0]], strlen($matches[0])];
			}
			if (
				// Tag.
				preg_match(
					'/^<(\/)?[a-z][a-z0-9\-]*
						(?(1)\s*|(?:\s+[a-z_:][a-z0-9_:\.\-]*(?:\s*=\s*
								(?(?=[\"\'])([\"\'])(?:(?!\2).)*\2|[^\s"\'=<>`]+)
						)?)*\s*\/?)>/isx',
					$text,
					$matches
				)
			) {
				return [['lt', $matches[0]], strlen($matches[0])];
			}
		}
		return [['text', '&lt;'], 1];
	}

	/**
	 * Renders inline HTML.
	 */
	protected function renderLt($block): string
	{
		return $block[1];
	}

	protected function parseGtMarkers(): array
	{
		return array('>');
	}

	/**
	 * Escapes `>` characters.
	 *
	 * @marker >
	 */
	protected function parseGt($text): array
	{
		return [['text', '&gt;'], 1];
	}

	protected function parseDoubleQuoteMarkers(): array
	{
		return array('"');
	}

	/**
	 * Escapes `"` characters.
	 *
	 * @marker "
	 */
	protected function parseDoubleQuote($text): array
	{
		return [['text', '&quot;'], 1];
	}

	abstract protected function renderText($block);
	abstract protected function EscapeHtmlEntities($text, $flags = 0);
	abstract protected function unEscapeHtmlEntities($text, $flags = 0);
}
