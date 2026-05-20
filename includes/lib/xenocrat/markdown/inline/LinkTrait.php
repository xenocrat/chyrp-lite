<?php
/**
 * @copyright Copyright 2014 Carsten Brandt, 2024-2026 Daniel Pimley
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
 * 	$this->references = [];
 * }
 * ```
 */
trait LinkTrait
{
	/**
	 * @var bool Render images with a deferred loading attribute.
	 */
	public $renderLazyImages = false;

	/**
	 * @var bool Enable support for defining intrinsic image dimensions.
	 *
	 * This enables `![title](url){width}` and `![title](url){width:height}`
	 * extended syntax to define intrinsic width and height of 1-999999999.
	 */
	public $enableImageDimensions = true;

	/**
	 * @var array - A list of defined references in this document.
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
			// Do not allow links within links.
			!in_array('parseLink', array_slice($this->context, 1))
			// Link?
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
			// Consume 1+ [ to avoid next one being parsed as a link.
			return [
				[
					'text',
					$markdown[0] . substr(
						$markdown,
						1,
						($spn = strspn($markdown, '[', 1))
					)
				],
				++$spn
			];
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
			($parts = $this->parseLinkOrImage(substr($markdown, 1))) !== false
		) {
			list($text, $url, $title, $offset, $key) = $parts;

			if (
				$this->enableImageDimensions
				&& str_starts_with(
					($dimensions = substr($markdown, $offset + 1, 21)),
					'{'
				)
				&& preg_match(
					'/^\{([0-9]{1,9})(:([0-9]{1,9}))?\}/',
					$dimensions,
					$dimensionMatches
				)
			) {
			// Intrinsic dimensions.
				$width = $dimensionMatches[1];
				$height = $dimensionMatches[3] ?? false;
				$offset += strlen($dimensionMatches[0]);
			}

			return [
				[
					'image',
					'text' => $text,
					'url' => $url,
					'title' => $title,
					'refkey' => $key,
					'width' => $width ?? false,
					'height' => $height ?? false,
					'orig' => substr($markdown, 0, $offset + 1),
				],
				$offset + 1
			];
		} else {
			// Consume 1+ [ to avoid next one being parsed as a link.
			return [
				[
					'text',
					$markdown[0] . substr(
						$markdown,
						1,
						($spn = strspn($markdown, '[', 1))
					)
				],
				++$spn
			];
		}
	}

	protected function parseLinkOrImage($markdown): array|false
	{
		if (strpos($markdown, ']', 1) === false) {
			return false;
		}

		$regexable = str_replace(
			'\\\\',
			'\\\\'.chr(31),
			$markdown
		);

		if (
			preg_match(
				'/(?(R)|^)\[((?>([^\[\]\\\\]|\\\\[\[\]]|\\\\)+|(?R))*)\]/',
				$regexable,
				$textMatches
			)
		) {
			$offset = strlen($textMatches[0]);

			$textMatches[0] = str_replace(
				'\\\\'.chr(31),
				'\\\\',
				$textMatches[0]
			);

			$textMatches[1] = str_replace(
				'\\\\'.chr(31),
				'\\\\',
				$textMatches[1]
			);

			$text = $textMatches[1];
			$consumed = strlen($textMatches[0]);
			$regexable = substr($regexable, $offset);

			$context = array_shift($this->context);

			$overrun = $this->detectInlineOverrun(
				$markdown,
				$consumed,
				['Lt', 'BracketedLink', 'InlineCode']
			);

			array_unshift($this->context, $context);

			if ($overrun) {
				// Inline HTML, bracketed link, or code takes precedence.
				return false;
			}

			if (
				preg_match(
					'/(?(R)
						# In case of pattern recursion match parentheses:
						\(((?>[^\s\\\\(\[\])]|\\\\[(\[\])]|\\\\)|(?R))*\)
						# Otherwise...
						|
						# Match opening parentheses:
						^\(\s*
						((
						# Match a bracketed link:
						(<(?>[^\n\\\\\<\[\]>]|\\\\[<\[\]>]|\\\\)*(?<!\\\\)>)
						# Or an unbracketed link:
						|(?!<)((?>[^\s\\\\(\[\])]|\\\\[(\[\])]|\\\\)|(?R))*
						))
						# Optional title:
						(
						\s+([\'"]|(\())((?>\\\\.|.(?<!(?(8)\)|\7)))*)
						(?<!\\\\)(?(8)\)|\7)
						)?
						# Match closing parentheses:
						\s*(?<!\\\\)\))/xs',
					$regexable,
					$refMatches
				)
			) {
			// Inline link.
				$refMatches[0] = str_replace(
					'\\\\'.chr(31),
					'\\\\',
					$refMatches[0]
				);

				$url = isset($refMatches[2]) ?
					str_replace(
						'\\\\'.chr(31),
						'\\\\',
						$refMatches[2]
					) :
					'';

				if (
					str_starts_with($url, '<')
					&& str_ends_with($url, '>')
				) {
					$url = str_replace(' ', '%20', substr($url, 1, -1));
				}

				$title = empty($refMatches[9]) ?
					null :
					str_replace(
						'\\\\'.chr(31),
						'\\\\',
						$refMatches[9]
					);

				return [
					$text,
					$url,
					$title,
					$consumed + strlen($refMatches[0]),
					null,
				];
			} elseif (
				preg_match(
					'/^(\[((?>\\\\[\[\]]|\\\\|[^\\\\\[\]]+)*?)(?<!\\\\)\])?/',
					$regexable,
					$refMatches
				)
			) {
			// Reference style link.
				$refMatches[0] = str_replace(
					'\\\\'.chr(31),
					'\\\\',
					$refMatches[0]
				);

				$key = empty($refMatches[2]) ?
					$text :
					str_replace(
						'\\\\'.chr(31),
						'\\\\',
						$refMatches[2]
					);

				$key = function_exists("mb_convert_case") ?
					mb_convert_case($key, MB_CASE_FOLD, 'UTF-8') :
					strtolower($key);

				$url = null;
				$title = null;

				return [
					$text,
					$url,
					$title,
					$consumed + strlen($refMatches[0]),
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
	protected function parseBracketedLink($markdown): array
	{
		if (strpos($markdown, '>') !== false) {
			if (
				// Do not allow links within links.
				!in_array('parseLink', $this->context)
			) {
				if (
					preg_match(
						'/^<([a-z][a-z0-9\+\.\-]{1,31}:[^\s<>]*)>/i',
						$markdown,
						$matches
					)
				) {
					// URL.
					return [
						[
							'url',
							$matches[1]
						],
						strlen($matches[0])
					];
				} elseif (
					preg_match('/^<([^\\\\\s>]*?@[^\s]*?\.\w+?)>/',
						$markdown, $matches
					)
				) {
					// Email address.
					return [
						[
							'email',
							$matches[1]
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
			ENT_NOQUOTES | ENT_SUBSTITUTE | ENT_DISALLOWED
		);

		return "<a href=\"mailto:$email\">$email</a>";
	}

	protected function renderUrl($block): string
	{
		$url = $this->escapeHtmlEntities($block[1], ENT_COMPAT);
		$decodedUrl = rawurldecode($block[1]);

		$secureUrlText = preg_match('//u', $decodedUrl) ?
			$decodedUrl :
			$block[1];

		$text = $this->escapeHtmlEntities(
			$secureUrlText,
			ENT_NOQUOTES | ENT_SUBSTITUTE | ENT_DISALLOWED
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
			if (
				($ref = $this->lookupReference($block['refkey'])) !== false
			) {
				$block = array_merge($block, $ref);
			} else {
				if (str_starts_with($block['orig'], '[')) {
					return '['
						. $this->renderAbsy(
							$this->parseInline(substr($block['orig'], 1))
						);
				}
				return $block['orig'];
			}
		}

		return '<a href="'
			. $this->escapeHtmlEntities(
				$this->unEscapeHtmlEntities(
					$this->unEscapeBackslash(
						$block['url']
					),
					ENT_QUOTES | ENT_SUBSTITUTE
				),
				ENT_COMPAT | ENT_SUBSTITUTE | ENT_DISALLOWED
			)
			. '"'
			. (
				empty($block['title']) ?
					'' :
					' title="' 
					. $this->escapeHtmlEntities(
						$this->unEscapeHtmlEntities(
							$this->unEscapeBackslash(
								$block['title']
							),
							ENT_QUOTES | ENT_SUBSTITUTE
						),
						ENT_COMPAT | ENT_SUBSTITUTE | ENT_DISALLOWED
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
				if (str_starts_with($block['orig'], '![')) {
					return '!['
					. $this->renderAbsy(
						$this->parseInline(substr($block['orig'], 2))
					);
				}
				return $block['orig'];
			}
		}

		return '<img src="'
			. $this->escapeHtmlEntities(
				$this->unEscapeHtmlEntities(
					$this->unEscapeBackslash(
						$block['url']
					),
					ENT_QUOTES | ENT_SUBSTITUTE
				),
				ENT_COMPAT | ENT_SUBSTITUTE | ENT_DISALLOWED
			)
			. '"'
			. ' alt="'
			. $this->escapeHtmlEntities(
				strip_tags(
					$this->renderAbsy(
						$this->parseInline($block['text'])
					)
				),
				ENT_COMPAT | ENT_SUBSTITUTE | ENT_DISALLOWED
			)
			. '"'
			. (
				empty($block['width']) ?
					'' :
					' width="' . $block['width'] . '"'
			)
			. (
				empty($block['height']) ?
					'' :
					' height="' . $block['height'] . '"'
			)
			. (
				$this->renderLazyImages ?
					' loading="lazy"' :
					''
			)
			. (
				empty($block['title']) ?
					'' :
					' title="'
					. $this->escapeHtmlEntities(
						$this->unEscapeHtmlEntities(
							$this->unEscapeBackslash(
								$block['title']
							),
							ENT_QUOTES | ENT_SUBSTITUTE
						),
						ENT_COMPAT | ENT_SUBSTITUTE | ENT_DISALLOWED
					)
					. '"'
			)
			. ($this->html5 ? '>' : ' />');
	}

	#---------------------------------------------
	# References
	#---------------------------------------------

	protected function identifyReference($line): bool
	{
		return (
			isset($line[0])
			&& ($line[0] === ' ' || $line[0] === '[')
			&& preg_match(
				'/^ {0,3}\[(.+?)(?<!\\\\)\]:\s*(([^\s]+?)(?:\s+[\'"](.+?)[\'"])?\s*)?$/',
				str_replace(
					'\\\\',
					'\\\\'.chr(31),
					$line
				)
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
				str_replace(
					'\\\\',
					'\\\\'.chr(31),
					$lines[$current]
				),
				$matches
			)
		) {
			if (preg_match('/(?<!\\\\)[\[\]]/', $matches[1])) {
			// Unescaped brackets - consume lines as paragraph.
				return $this->consumeParagraph($lines, $current);
			}

			$matches[1] = str_replace(
				'\\\\'.chr(31),
				'\\\\',
				$matches[1]
			);

			$key = function_exists("mb_convert_case") ?
				mb_convert_case($matches[1], MB_CASE_FOLD, 'UTF-8') :
				strtolower($matches[1]);

			if (isset($matches[2])) {
				$matches[2] = str_replace(
					'\\\\'.chr(31),
					'\\\\',
					$matches[2]
				);
				$url = $matches[2];
			} else {
			// URL may be on the next line.
				if (
					isset($lines[$current + 1])
					&& ($url = trim($lines[$current + 1])) !== ''
				) {
					$current++;
				} else {
				// URL not found - consume lines as paragraph.
					return $this->consumeParagraph($lines, $current);
				}
			}

			if (str_starts_with($url, '<') && str_ends_with($url, '>')) {
				$url = str_replace(' ', '%20', substr($url, 1, -1));
			}

			$ref = [
				'url' => $url,
			];

			if (isset($matches[3])) {
				$matches[3] = str_replace(
					'\\\\'.chr(31),
					'\\\\',
					$matches[3]
				);
				$ref['title'] = $matches[3];
			} else {
			// Title may be on the next line.
				if (
					isset($lines[$current + 1])
					&& preg_match(
						'/^\s*[\(\'"](.+?)[\)\'"]\s*$/',
						$lines[$current + 1],
						$matches
					)
				) {
					$ref['title'] = $matches[1];
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

	abstract protected function renderText($block);
	abstract protected function consumeParagraph($lines, $current);
	abstract protected function parseInline($text);
	abstract protected function renderAbsy($blocks);
	abstract protected function unEscapeBackslash($text);
	abstract protected function escapeHtmlEntities($text, $flags = 0);
	abstract protected function unescapeHtmlEntities($text, $flags = 0);
}
