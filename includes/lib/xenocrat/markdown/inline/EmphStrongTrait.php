<?php
/**
 * @copyright Copyright 2014 Carsten Brandt, 2024-2026 Daniel Pimley
 * @license https://github.com/xenocrat/chyrp-markdown/blob/master/LICENSE
 * @link https://github.com/xenocrat/chyrp-markdown#readme
 */

namespace xenocrat\markdown\inline;

/**
 * Adds inline emphasizes and strong elements.
 */
trait EmphStrongTrait
{
	protected function parseEmphStrongMarkers(): array
	{
		return array('_', '*');
	}

	/**
	 * Parses emphasized and strong elements.
	 *
	 * @marker _
	 * @marker *
	 * @see https://www.unicode.org/reports/tr44/#General_Category_Values
	 */
	protected function parseEmphStrong($markdown): array
	{
		if (!isset($markdown[1])) {
			return [['text', $markdown[0]], 1];
		}
		if (($marker = $markdown[0]) == $markdown[1]) {
		// Strong.
			// Avoid excessive regex backtracking if there is no closing marker.
			if (strpos($markdown, $marker . $marker, 2) === false) {
				return [['text', $markdown[0]], 1];
			}
			$regexable = str_replace(
				'\\\\',
				'\\\\'.chr(31),
				$markdown
			);
			if (
				$marker === '*'
				&& preg_match(
					'/^
						# Opening marker:
						[*]{2}
						# First char cannot be whitespace.
						# First char cannot be Unicode category Zs, Pe, Pf.
						(?![\s\p{Zs}\p{Pe}\p{Pf}])
						# Capture two or more matched backticks (possible code)
						# matched nested markers, escaped marker, or other char:
						((?>(`{2,})(?!`).*?[^`]\2(?!`)|([*]+)[^*]*\3|\\\\[*]|[^*])+?)
						# Last char cannot be whitespace.
						# Last char cannot be Unicode category Zs, Ps, Pi.
						(?<![\s\p{Zs}\p{Ps}\p{Pi}])
						# Closing marker:
						[*]{2}/usx',
					$regexable,
					$matches
				)
				|| $marker === '_'
				&& preg_match(
					'/^
						# Opening marker:
						__
						# First char cannot be whitespace.
						# First char cannot be Unicode category Zs, Pe, Pf.
						(?![\s\p{Zs}\p{Pe}\p{Pf}])
						# Capture two or more matched backticks (possible code)
						# matched nested markers, escaped marker, or other char:
						((?>(`{2,})(?!`).*?[^`]\2(?!`)|(_+)[^_]*\3|\\\\_|[^_])+?)
						# Last char cannot be whitespace.
						# Last char cannot be Unicode category Zs, Ps, Pi.
						(?<![\s\p{Zs}\p{Ps}\p{Pi}])
						# Closing marker:
						__
						# Next char after the closing marker must be non-word.
						\b/usx',
					$regexable,
					$matches
				)
			) {
				$matches[0] = str_replace(
					'\\\\'.chr(31),
					'\\\\',
					$matches[0]
				);
				$matches[1] = str_replace(
					'\\\\'.chr(31),
					'\\\\',
					$matches[1]
				);
				if (
					// Inline HTML, link, image, or code takes precedence.
					!$this->detectInlineOverrun(
						$markdown,
						strlen($matches[0]),
						['Lt', 'Link', 'Image', 'InlineCode']
					)
				) {
					return [
						[
							'strong',
							$this->parseInline($matches[1]),
						],
						strlen($matches[0])
					];
				}
			}
		} else {
		// Emphasis
			// Avoid excessive regex backtracking if there is no closing marker.
			if (strpos($markdown, $marker, 1) === false) {
				return [['text', $markdown[0]], 1];
			}
			$regexable = str_replace(
				'\\\\',
				'\\\\'.chr(31),
				$markdown
			);
			if (
				$marker === '*'
				&& preg_match(
					'/^
						# Opening marker:
						[*]
						# First char cannot be whitespace.
						# First char cannot be Unicode category Zs, Pe, Pf.
						(?![\s\p{Zs}\p{Pe}\p{Pf}])
						# Capture two or more matched backticks (possible code)
						# matched nested markers, escaped marker, or other char.
						((?>(`{2,})(?!`).*?[^`]\2(?!`)|([*]+)[^*]*\3|\\\\[*]|[^*])+?)
						# Last char cannot be whitespace.
						# Last char cannot be Unicode category Zs, Ps, Pi.
						(?<![\s\p{Zs}\p{Ps}\p{Pi}])
						# Closing marker:
						[*]
						# Emphasis closing marker cannot form a strong marker.
						(?![*][^*])/usx',
					$regexable,
					$matches
				)
				|| $marker === '_'
				&& preg_match(
					'/^
						# Opening marker:
						_
						# First char cannot be whitespace.
						# First char cannot be Unicode category Zs, Pe, Pf.
						(?![\s\p{Zs}\p{Pe}\p{Pf}])
						# Capture two or more matched backticks (possible code)
						# matched nested markers, escaped marker, or other char.
						((?>(`{2,})(?!`).*?[^`]\2(?!`)|(_+)[^_]*\3|\\\\_|[^_])+?)
						# Last char cannot be whitespace.
						# Last char cannot be Unicode category Zs, Ps, Pi.
						(?<![\s\p{Zs}\p{Ps}\p{Pi}])
						# Closing marker:
						_
						# Emphasis closing marker cannot form a strong marker.
						(?!_[^_])
						# Next char after the closing marker must be non-word.
						\b/usx',
					$regexable,
					$matches
				)
			) {
				$matches[0] = str_replace(
					'\\\\'.chr(31),
					'\\\\',
					$matches[0]
				);
				$matches[1] = str_replace(
					'\\\\'.chr(31),
					'\\\\',
					$matches[1]
				);
				if (
					// Inline HTML, link, image, or code takes precedence.
					!$this->detectInlineOverrun(
						$markdown,
						strlen($matches[0]),
						['Lt', 'Link', 'Image', 'InlineCode']
					)
				) {
					return [
						[
							'emph',
							$this->parseInline($matches[1]),
						],
						strlen($matches[0])
					];
				}
			}
		}
		return [['text', $markdown[0]], 1];
	}

	protected function renderStrong($block): string
	{
		return '<strong>'
			. $this->renderAbsy($block[1])
			. '</strong>';
	}

	protected function renderEmph($block): string
	{
		return '<em>'
			. $this->renderAbsy($block[1])
			. '</em>';
	}

	abstract protected function detectInlineOverrun($text, $length, $elements);
	abstract protected function renderText($block);
	abstract protected function parseInline($text);
	abstract protected function renderAbsy($blocks);
}
