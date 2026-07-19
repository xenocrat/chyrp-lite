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

	protected function parseLinkMarkers(
	): array {
		return array('[');
	}

	/**
	 * Parses a link indicated by `[`.
	 *
	 * @marker [
	 */
	protected function parseLink(
		$markdown
	): array {
		if (
			// Do not allow links within links.
			!in_array('parseLink', array_slice($this->context, 1))
			// Link?
			&& ($parts = $this->parseLinkOrImage($markdown)) !== false
		) {
			list($text, $url, $title, $offset, $label) = $parts;

			return [
				[
					'link',
					'text' => $this->parseInline($text),
					'url' => $url,
					'title' => $title,
					'label' => $label,
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

	protected function parseImageMarkers(
	): array {
		return array('![');
	}

	/**
	 * Parses an image indicated by `![`.
	 *
	 * @marker ![
	 */
	protected function parseImage(
		$markdown
	): array {
		if (
			($parts = $this->parseLinkOrImage(substr($markdown, 1))) !== false
		) {
			list($text, $url, $title, $offset, $label) = $parts;

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
					'label' => $label,
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

	protected function parseLinkOrImage(
		$markdown
	): array|false {
		if (strpos($markdown, ']', 1) === false) {
			return false;
		}

		if (
			preg_match(
				'/(?(R)|^)
					\[((?>(?:\\\\.|[^\[\]\\\\])+|(?R))*)\]/x',
				$markdown,
				$textMatches
			)
		) {
			$offset = strlen($textMatches[0]);
			$text = $textMatches[1];
			$substr = substr($markdown, $offset);
			$context = array_shift($this->context);

			$overrun = $this->detectInlineOverrun(
				$markdown,
				$offset,
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
						\(((?>\\\\[^\s]|[^\s\\\\(\[\])])|(?R))*\)
						# Otherwise...
						|
						# Match opening parentheses:
						^\(\s*
						(?:
						(
						# Match a bracketed link:
						<(?>\\\\[^\n]|[^\n\\\\\<\[\]>])*>
						# Or an unbracketed link:
						|(?!<)(?:(?>\\\\[^ \t\n]|[^ \t\n\\\\(\[\])])|(?R))+
						)
						# Match an optional title:
						(?:
						[ \t\n]+
						([\'"]|(\())
						((?>\\\\.|(?!(?(4)\)|\3))[^\\\\])*)
						(?(4)\)|\3)
						)?
						)?
						# Match closing parentheses:
						\s*\))/xs',
					$substr,
					$refMatches
				)
			) {
			// Inline link.
				$url = $refMatches[2] ?? '';

				if (
					str_starts_with($url, '<')
					&& str_ends_with($url, '>')
				) {
					$url = str_replace(' ', '%20', substr($url, 1, -1));
				}

				$title = (isset($refMatches[5]) && $refMatches[5] !== '') ?
					$refMatches[5] :
					null;

				return [
					$text,
					$url,
					$title,
					$offset + strlen($refMatches[0]),
					null,
				];
			} elseif (
				preg_match(
					'/^(\[((?>\\\\.|[^\\\\\[\]])*)\])?/',
					$substr,
					$refMatches
				)
			) {
			// Reference style link.
				$label = (isset($refMatches[2]) && $refMatches[2] !== '') ?
					$refMatches[2] :
					$text;

				$label = function_exists("mb_convert_case") ?
					mb_convert_case($label, MB_CASE_FOLD, 'UTF-8') :
					strtolower($label);

				$url = null;
				$title = null;

				return [
					$text,
					$url,
					$title,
					$offset + strlen($refMatches[0]),
					$label,
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
	protected function parseBracketedLink(
		$markdown
	): array {
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
					preg_match(
						'/^<([\w\d\.!\#$%&\'*+\/=?^_`{|}~\-]+@
							(?:[a-z0-9\-_]+\.)+[a-z0-9\-_]*[a-z0-9]+)>/iux',
						$markdown,
						$matches
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

	protected function renderEmail(
		$block
	): string {
		$email = $this->escapeHtmlEntities(
			$block[1],
			ENT_NOQUOTES | ENT_SUBSTITUTE | ENT_DISALLOWED
		);

		return "<a href=\"mailto:$email\">$email</a>";
	}

	protected function renderUrl(
		$block
	): string {
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

	protected function lookupReference(
		$label
	): array|false {
		$normalizedKey =
			preg_replace('/\s+/', ' ', $label)
			?? $label;

		if (
			isset($this->references[$label])
			|| isset($this->references[$label = $normalizedKey])
		) {
			return $this->references[$label];
		}

		return false;
	}

	protected function renderLink(
		$block
	): string {
		if (isset($block['label'])) {
			if (
				($ref = $this->lookupReference($block['label'])) !== false
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

	protected function renderImage(
		$block
	): string {
		if (isset($block['label'])) {
			if (($ref = $this->lookupReference($block['label'])) !== false) {
				$block = array_merge($block, $ref);
			} else {
				if (str_starts_with($block['orig'], '![')) {
					return '![' . $this->renderAbsy(
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

	protected function identifyReference(
		$line
	): bool {
		return (
			isset($line[0])
			&& ($line[0] === ' ' || $line[0] === '[')
			&& preg_match(
					'/(?(R)
						# In case of pattern recursion match parentheses:
						\(((?>\\\\[^\s]|[^\s\\\\(\[\])])|(?R))*\)
						# Otherwise...
						|
						# Match the link label:
						^[ ]{0,3}
						\[((?:\\\\.|[^\\\\\[\]])+)\]:\s*
						(?:
						(
						# Match a bracketed link:
						<(?>\\\\.|[^\\\\\<\[\]>]+)*>
						# Or an unbracketed link:
						|(?!<)(?:(?>\\\\[^ \t]|[^ \t\\\\(\[\])])|(?R))+
						)
						# Match an optional title:
						(?:
						[ \t]+
						([\'"]|(\())
						((?>\\\\.|(?!(?(5)\)|\4))[^\\\\])*)
						(?(5)\)|\4)
						)?
						)?
						# Allow trailing space but nothing else:
						\s*$)/x',
				$line
			)
		);
	}

	/**
	 * Consume link references.
	 */
	protected function consumeReference(
		$lines,
		$current
	): array {
		while (
			isset($lines[$current])
			&& preg_match(
					'/(?(R)
						# In case of pattern recursion match parentheses:
						\(((?>\\\\[^\s]|[^\s\\\\(\[\])])|(?R))*\)
						# Otherwise...
						|
						# Match the link label:
						^[ ]{0,3}
						\[((?:\\\\.|[^\\\\\[\]])+)\]:\s*
						(?:
						(
						# Match a bracketed link:
						<(?>\\\\.|[^\\\\\<\[\]>]+)*>
						# Or an unbracketed link:
						|(?!<)(?:(?>\\\\[^ \t]|[^ \t\\\\(\[\])])|(?R))+
						)
						# Match an optional title:
						(?:
						[ \t]+
						([\'"]|(\())
						((?>\\\\.|(?!(?(5)\)|\4))[^\\\\])*)
						(?(5)\)|\4)
						)?
						)?
						# Allow trailing space but nothing else:
						\s*$)/x',
				$lines[$current],
				$matches
			)
		) {
			$label = function_exists("mb_convert_case") ?
				mb_convert_case($matches[2], MB_CASE_FOLD, 'UTF-8') :
				strtolower($matches[2]);

			if (isset($matches[3])) {
				$url = $matches[3];
			} else {
			// URL may be on the next line.
				if (
					isset($lines[$current + 1])
					&& preg_match(
						'/(?(R)
							# In case of pattern recursion match parentheses:
							\(((?>\\\\[^\s]|[^\s\\\\(\[\])])|(?R))*\)
							# Otherwise...
							|
							# Optional leading spaces:
							^\s*
							(
							# Match a bracketed link:
							<(?>\\\\.|[^\\\\\<\[\]>])*>
							# Or an unbracketed link:
							|(?!<)(?:(?>\\\\[^\s]|[^\s\\\\(\[\])])|(?R))+
							)
							# Allow trailing spaces but nothing else:
							\s*$)/x',
						$lines[$current + 1],
						$url_matches
					)
				) {
					$url = $url_matches[2];
					$current++;
				} else {
				// URL not found - consume lines as paragraph.
					return $this->consumeParagraph($lines, $current);
				}
			}

			if (
				str_starts_with($url, '<')
				&& str_ends_with($url, '>')
			) {
				$url = str_replace(' ', '%20', substr($url, 1, -1));
			}

			$ref = ['url' => $url];

			if (isset($matches[6])) {
				$ref['title'] = $matches[6];
			} else {
			// Title may be on the next line.
				if (
					isset($lines[$current + 1])
					&& preg_match(
						'/^\s*
							# Match a title:
							(?:
							([\'"]|(\())
							((?>\\\\.|(?!(?(2)\)|\1))[^\\\\])*)
							(?(2)\)|\1)
							)
							# Allow trailing space but nothing else:
							\s*$/x',
						$lines[$current + 1],
						$title_matches
					)
				) {
					$ref['title'] = $title_matches[3];
					$current++;
				}
			}

			if (!isset($this->references[$label])) {
				$this->references[$label] = $ref;
			}

			$current++;
		}

		return [false, --$current];
	}

	abstract protected function consumeParagraph(
		$lines,
		$current
	);

	abstract protected function escapeHtmlEntities(
		$text,
		$flags = 0
	);

	abstract protected function parseInline(
		$text
	);

	abstract protected function renderAbsy(
		$blocks
	);

	abstract protected function renderText(
		$block
	);

	abstract protected function unEscapeBackslash(
		$text
	);

	abstract protected function unescapeHtmlEntities(
		$text,
		$flags = 0
	);
}
