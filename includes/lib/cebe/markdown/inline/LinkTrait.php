<?php
/**
 * @copyright Copyright (c) 2014 Carsten Brandt
 * @license https://github.com/xenocrat/chyrp-markdown/blob/master/LICENSE
 * @link https://github.com/xenocrat/chyrp-markdown#readme
 */

namespace cebe\markdown\inline;

/**
 * Adds links and images, and bracketed URLs.
 *
 * Make sure to reset references on prepare():
 *
 * ```php
 * protected function prepare()
 * {
 *     // reset references
 *     $this->references = [];
 * }
 * ```
 */
trait LinkTrait
{
	/**
	 * @var array a list of defined references in this document.
	 */
	protected $references = [];

	/**
	 * Remove backslash from escaped characters
	 * @param $text
	 * @return string
	 */
	protected function replaceEscape($text): string
	{
		$strtr = [];
		foreach($this->escapeCharacters as $char) {
			$strtr["\\$char"] = $char;
		}
		return strtr($text, $strtr);
	}

	protected function parseLinkMarkers(): array
	{
		return array('[');
	}

	/**
	 * Parses a link indicated by `[`.
	 * @marker [
	 */
	protected function parseLink($markdown): array
	{
		if (!in_array('parseLink', array_slice($this->context, 1))
			&& ($parts = $this->parseLinkOrImage($markdown)) !== false) {
			list($text, $url, $title, $offset, $key) = $parts;
			return [
				[
					'link',
					'text' => $this->parseInline($text),
					'url' => $url,
					'title' => $title,
					'refkey' => $key,
					'orig' => substr($markdown, 0, $offset),
				],
				$offset
			];
		} else {
			// remove all starting [ markers to avoid next one to be parsed as link
			$result = '[';
			$i = 1;
			while (isset($markdown[$i]) && $markdown[$i] === '[') {
				$result .= '[';
				$i++;
			}
			return [['text', $result], $i];
		}
	}

	protected function parseImageMarkers(): array
	{
		return array('![');
	}

	/**
	 * Parses an image indicated by `![`.
	 * @marker ![
	 */
	protected function parseImage($markdown): array
	{
		if (($parts = $this->parseLinkOrImage(substr($markdown, 1))) !== false) {
			list($text, $url, $title, $offset, $key) = $parts;

			return [
				[
					'image',
					'text' => $text,
					'url' => $url,
					'title' => $title,
					'refkey' => $key,
					'orig' => substr($markdown, 0, $offset + 1),
				],
				$offset + 1
			];
		} else {
			// remove all starting [ markers to avoid next one to be parsed as link
			$result = '!';
			$i = 1;
			while (isset($markdown[$i]) && $markdown[$i] === '[') {
				$result .= '[';
				$i++;
			}
			return [['text', $result], $i];
		}
	}

	protected function parseLinkOrImage($markdown): array|false
	{
		if (strpos($markdown, ']') !== false
			&& preg_match('/\[((?>[^\]\[]+|(?R))*)\]/', $markdown, $textMatches)) {
			$text = $textMatches[1];
			$offset = strlen($textMatches[0]);
			$markdown = substr($markdown, $offset);

			$pattern = <<<REGEXP
				/(?(R) # in case of recursion match parentheses
					 \(((?>[^\s()]+)|(?R))*\)
				|      # else match a link with title
					^\(\s*(((?>[^\s()]+)|(?R))*)(\s+"(.*?)")?\s*\)
				)/x
REGEXP;
			if (preg_match($pattern, $markdown, $refMatches)) {
				// inline link
				$url = isset($refMatches[2]) ? $this->replaceEscape($refMatches[2]) : '';
				$title = empty($refMatches[5]) ? null : $refMatches[5];
				$key = null;
				return [
					$text,
					$url,
					$title,
					$offset + strlen($refMatches[0]),
					$key,
				];
			} elseif (preg_match('/^([ \n]?\[(.*?)\])?/s', $markdown, $refMatches)) {
				// reference style link
				if (empty($refMatches[2])) {
					$key = strtolower($text);
				} else {
					$key = strtolower($refMatches[2]);
				}
				$url = null;
				$title = null;
				return [
					$text,
					$url,
					$title,
					$offset + strlen($refMatches[0]),
					$key,
				];
			}
		}
		return false;
	}

	protected function parseLtMarkers(): array
	{
		return array('<');
	}

	/**
	 * Parses inline HTML.
	 * @marker <
	 */
	protected function parseLt($text): array
	{
		if (strpos($text, '>') !== false) {
			if (!in_array('parseLink', $this->context)) {
			// do not allow links in links
				if (preg_match('/^<([^\s>]*?@[^\s]*?\.\w+?)>/', $text, $matches)) {
					// email address
					return [
						['email', $this->replaceEscape($matches[1])],
						strlen($matches[0])
					];
				} elseif (preg_match('/^<([a-z]{3,}:\/\/[^\s]+?)>/', $text, $matches)) {
					// URL
					return [
						['url', $this->replaceEscape($matches[1])],
						strlen($matches[0])
					];
				}
			}
			// try inline HTML if it was neither a URL nor email if HtmlTrait is included.
			if (method_exists($this, 'parseInlineHtml')) {
				return $this->parseInlineHtml($text);
			}
		}
		return [['text', '&lt;'], 1];
	}

	protected function renderEmail($block): string
	{
		$email = htmlspecialchars($block[1], ENT_NOQUOTES | ENT_SUBSTITUTE, 'UTF-8');
		return "<a href=\"mailto:$email\">$email</a>";
	}

	protected function renderUrl($block): string
	{
		$ent = $this->html5 ? ENT_HTML5 : ENT_HTML401;
		$url = htmlspecialchars($block[1], ENT_COMPAT | $ent, 'UTF-8');
		$decodedUrl = urldecode($block[1]);
		$secureUrlText = preg_match('//u', $decodedUrl) ? $decodedUrl : $block[1];
		$text = htmlspecialchars($secureUrlText, ENT_NOQUOTES | ENT_SUBSTITUTE, 'UTF-8');
		return "<a href=\"$url\">$text</a>";
	}

	protected function lookupReference($key): array|false
	{
		$normalizedKey = preg_replace('/\s+/', ' ', $key);
		if (isset($this->references[$key]) || isset($this->references[$key = $normalizedKey])) {
			return $this->references[$key];
		}
		return false;
	}

	protected function renderLink($block): string
	{
		if (isset($block['refkey'])) {
			if (($ref = $this->lookupReference($block['refkey'])) !== false) {
				$block = array_merge($block, $ref);
			} else {
				if (strncmp($block['orig'], '[', 1) === 0) {
					return '[' . $this->renderAbsy($this->parseInline(substr($block['orig'], 1)));
				}
				return $block['orig'];
			}
		}
		$ent = $this->html5 ? ENT_HTML5 : ENT_HTML401;
		return '<a href="'
			. htmlspecialchars($block['url'], ENT_COMPAT | $ent, 'UTF-8') . '"'
			. (empty($block['title']) ?
				'' :
				' title="' 
				. htmlspecialchars($block['title'], ENT_COMPAT | $ent | ENT_SUBSTITUTE, 'UTF-8') . '"')
			. '>' . $this->renderAbsy($block['text']) . '</a>';
	}

	protected function renderImage($block): string
	{
		if (isset($block['refkey'])) {
			if (($ref = $this->lookupReference($block['refkey'])) !== false) {
				$block = array_merge($block, $ref);
			} else {
				if (strncmp($block['orig'], '![', 2) === 0) {
					return '![' . $this->renderAbsy($this->parseInline(substr($block['orig'], 2)));
				}
				return $block['orig'];
			}
		}
		$ent = $this->html5 ? ENT_HTML5 : ENT_HTML401;
		return '<img src="' . htmlspecialchars($block['url'], ENT_COMPAT | $ent, 'UTF-8') . '"'
			. ' alt="'
			. htmlspecialchars($block['text'], ENT_COMPAT | $ent | ENT_SUBSTITUTE, 'UTF-8') . '"'
			. (empty($block['title']) ?
				'' :
				' title="'
				. htmlspecialchars($block['title'], ENT_COMPAT | $ent | ENT_SUBSTITUTE, 'UTF-8') . '"')
			. ($this->html5 ? '>' : ' />');
	}

	// references

	protected function identifyReference($line): bool
	{
		return isset($line[0]) && ($line[0] === ' ' || $line[0] === '[')
			&& preg_match('/^ {0,3}\[[^\[](.*?)\]:\s*([^\s]+?)(?:\s+[\'"](.+?)[\'"])?\s*$/', $line);
	}

	/**
	 * Consume link references
	 */
	protected function consumeReference($lines, $current): array
	{
		while (isset($lines[$current])
			&& preg_match('/^ {0,3}\[(.+?)\]:\s*(.+?)(?:\s+[\(\'"](.+?)[\)\'"])?\s*$/', $lines[$current], $matches)) {
			$label = strtolower($matches[1]);

			$this->references[$label] = [
				'url' => $this->replaceEscape($matches[2]),
			];
			if (isset($matches[3])) {
				$this->references[$label]['title'] = $matches[3];
			} else {
				// title may be on the next line
				if (isset($lines[$current + 1])
					&& preg_match('/^\s+[\(\'"](.+?)[\)\'"]\s*$/', $lines[$current + 1], $matches)) {
					$this->references[$label]['title'] = $matches[1];
					$current++;
				}
			}
			$current++;
		}
		return [false, --$current];
	}

	abstract protected function parseInline($text);
	abstract protected function renderAbsy($blocks);
}
