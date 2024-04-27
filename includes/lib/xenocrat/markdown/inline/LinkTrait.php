<?php
/**
 * @copyright Copyright (c) 2014 Carsten Brandt, 2024 Daniel Pimley
 * @license https://github.com/xenocrat/chyrp-markdown/blob/master/LICENSE
 * @link https://github.com/xenocrat/chyrp-markdown#readme
 */

namespace xenocrat\markdown\inline;

/**
 * Adds links and images, and bracketed URLs.
 *
 * Make sure to reset references on prepare():
 *
 * ```php
 * protected function prepare()
 * {
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

	protected function parseLinkMarkers(): array
	{
		return array('[');
	}

	/**
	 * Parses a link indicated by `[`.
	 *
	 * @marker [
	 */
	protected function parseLink($markdown): array
	{
		if (
			!in_array('parseLink', array_slice($this->context, 1))
			&& ($parts = $this->parseLinkOrImage($markdown)) !== false
		) {
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
	 *
	 * @marker ![
	 */
	protected function parseImage($markdown): array
	{
		if (
			($parts = $this->parseLinkOrImage(substr($markdown, 1)))
			!== false
		) {
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
		if (
			strpos($markdown, ']') !== false
			&& preg_match('/^\[(.*?)(?<!\\\\)\]/', $markdown, $textMatches)
			// unescaped brackets are not allowed
			&& !preg_match('/(?<!\\\\)[\[\]]/', $textMatches[1])
		) {
			$text = $textMatches[1];
			$offset = strlen($textMatches[0]);
			$markdown = substr($markdown, $offset);

			$pattern = <<<REGEXP
				/(?(R) # in case of recursion match parentheses
					 \(((?>[^\s()]+)|(?R))*\)
				|      # else match a link with title
					^\(\s*(((?><[^<>\n]+>)|(?>[^\s()]+)|(?R))*)(\s+"(.*?)")?\s*\)
				)/xs
REGEXP;

			if (preg_match($pattern, $markdown, $refMatches)) {
			// inline link
				$url = isset($refMatches[2]) ?
					$this->unEscapeBackslash($refMatches[2]) : '' ;

				$lt = str_starts_with($url, '<');
				$gt = str_ends_with($url, '>');
				if (($lt && !$gt) || (!$lt && $gt)) {
				// improperly matched brackets
					return false;
				}
				if ($lt && $gt) {
					$url = str_replace(' ', '%20', substr($url, 1, -1));
				}
				$title = empty($refMatches[5]) ?
					null : $this->unEscapeBackslash($refMatches[5]) ;

				$key = null;

				return [
					$text,
					$url,
					$title,
					$offset + strlen($refMatches[0]),
					$key,
				];
			} elseif (preg_match('/^(\[(.*?)\])?/s', $markdown, $refMatches)) {
			// reference style link
				$key = empty($refMatches[2]) ? $text : $refMatches[2];

				$key = function_exists("mb_convert_case") ?
					mb_convert_case($key, MB_CASE_FOLD, 'UTF-8') :
					strtolower($key) ;

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

	/**
	 * Parses bracketed URL or email.
	 *
	 * @marker <
	 */
	protected function parseBracketedLink($text): array
	{
		if (strpos($text, '>') !== false) {
			if (!in_array('parseLink', $this->context)) {
			// do not allow links in links
				if (
					preg_match(
						'/^<([a-z][a-z0-9\+\.\-]{1,31}:[^\s<>]*)>/i',
						$text,
						$matches
					)
				) {
					// URL
					return [
						[
							'url',
							$this->unEscapeBackslash($matches[1])
						],
						strlen($matches[0])
					];
				} elseif (
					preg_match('/^<([^\\\\\s>]*?@[^\s]*?\.\w+?)>/',
						$text, $matches
					)
				) {
					// email address
					return [
						[
							'email',
							$this->unEscapeBackslash($matches[1])
						],
						strlen($matches[0])
					];
				}
			}
		}
		return [['text', '&lt;'], 1];
	}

	protected function renderEmail($block): string
	{
		$email = $this->escapeHtmlEntities(
			$block[1],
			ENT_NOQUOTES | ENT_SUBSTITUTE
		);
		return "<a href=\"mailto:$email\">$email</a>";
	}

	protected function renderUrl($block): string
	{
		$url = $this->escapeHtmlEntities($block[1], ENT_COMPAT);
		$decodedUrl = rawurldecode($block[1]);

		$secureUrlText = preg_match('//u', $decodedUrl) ?
			$decodedUrl : $block[1];

		$text = $this->escapeHtmlEntities(
			$secureUrlText,
			ENT_NOQUOTES | ENT_SUBSTITUTE
		);
		return "<a href=\"$url\">$text</a>";
	}

	protected function lookupReference($key): array|false
	{
		$normalizedKey = preg_replace('/\s+/', ' ', $key);
		if (
			isset($this->references[$key])
			|| isset($this->references[$key = $normalizedKey])
		) {
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
					return '['
						. $this->renderAbsy(
							$this->parseInline(substr($block['orig'], 1))
						);
				}
				return $block['orig'];
			}
		}
		return '<a href="'
			. $this->escapeHtmlEntities($block['url'], ENT_COMPAT) . '"'
			. (
				empty($block['title']) ?
					'' :
					' title="' 
					. $this->escapeHtmlEntities(
						$block['title'],
						ENT_COMPAT | ENT_SUBSTITUTE
					)
					. '"'
				)
			. '>' . $this->renderAbsy($block['text']) . '</a>';
	}

	protected function renderImage($block): string
	{
		if (isset($block['refkey'])) {
			if (($ref = $this->lookupReference($block['refkey'])) !== false) {
				$block = array_merge($block, $ref);
			} else {
				if (strncmp($block['orig'], '![', 2) === 0) {
					return '!['
					. $this->renderAbsy(
						$this->parseInline(substr($block['orig'], 2))
					);
				}
				return $block['orig'];
			}
		}
		return '<img src="'
			. $this->escapeHtmlEntities($block['url'], ENT_COMPAT) . '"'
			. ' alt="'
			. $this->escapeHtmlEntities(
				$block['text'],
				ENT_COMPAT | ENT_SUBSTITUTE
			)
			. '"'
			. (
				empty($block['title']) ?
					'' :
					' title="'
					. $this->escapeHtmlEntities(
						$block['title'],
						ENT_COMPAT | ENT_SUBSTITUTE
					)
					. '"'
				)
			. ($this->html5 ? '>' : ' />');
	}

	// references

	protected function identifyReference($line): bool
	{
		return (
			isset($line[0])
			&& ($line[0] === ' ' || $line[0] === '[')
			&& preg_match(
				'/^ {0,3}\[(.+?)(?<!\\\\)\]:\s*(([^\s]+?)(?:\s+[\'"](.+?)[\'"])?\s*)?$/',
				$line
			)
		);
	}

	/**
	 * Consume link references.
	 */
	protected function consumeReference($lines, $current): array
	{
		while (
			isset($lines[$current])
			&& preg_match(
				'/^ {0,3}\[(.+?)(?<!\\\\)\]:\s*(?:(.+?)(?:\s+[\(\'"](.+?)[\)\'"])?\s*)?$/',
				$lines[$current],
				$matches
			)
		) {
			if (preg_match('/(?<!\\\\)[\[\]]/', $matches[1])) {
			// unescaped brackets are not allowed
				return $this->consumeParagraph($lines, $current);
			}
			$key = function_exists("mb_convert_case") ?
				mb_convert_case($matches[1], MB_CASE_FOLD, 'UTF-8') :
				strtolower($matches[1]) ;

			if (isset($matches[2])) {
				$url = $matches[2];
			} else {
			// url may be on the next line
				if (
					isset($lines[$current + 1])
					&& trim($lines[$current + 1]) !== ''
				) {
					$url = trim($lines[$current + 1]);
					$current++;
				} else {
				// url not found - consume lines as paragraph
					return $this->consumeParagraph($lines, $current);
				}
			}
			if (str_starts_with($url, '<') && str_ends_with($url, '>')) {
				$url = str_replace(' ', '%20', substr($url, 1, -1));
			}
			$ref = [
				'url' => $this->unEscapeBackslash($url),
			];
			if (isset($matches[3])) {
				$ref['title'] = $this->unEscapeBackslash($matches[3]);
			} else {
			// title may be on the next line
				if (
					isset($lines[$current + 1])
					&& preg_match(
						'/^\s*[\(\'"](.+?)[\)\'"]\s*$/',
						$lines[$current + 1],
						$matches
					)
				) {
					$ref['title'] = $this->unEscapeBackslash($matches[1]);
					$current++;
				}
			}
			if (!isset($this->references[$key])) {
				$this->references[$key] = $ref;
			}
			$current++;
		}

		return [false, --$current];
	}

	abstract protected function parseInline($text);
	abstract protected function renderAbsy($blocks);
}
