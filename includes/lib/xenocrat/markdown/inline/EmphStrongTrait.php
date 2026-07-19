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
	protected function parseEmphStrongMarkers(
	): array {
		return array('_', '*');
	}

	/**
	 * Parses emphasized and strong elements.
	 *
	 * @marker _
	 * @marker *
	 * @see https://www.unicode.org/reports/tr44/#General_Category_Values
	 */
	protected function parseEmphStrong(
		$markdown,
		$preceding
	): array {
		if (!isset($markdown[1])) {
			return [['text', $markdown[0]], 1];
		}

		if (
			($marker = $markdown[0]) == $markdown[1]
			// Closing marker?
			&& strpos($markdown, $marker . $marker, 2) !== false
		) {
		// Strong.
			if (
				$marker === '*'
				&& preg_match(
					'/
						# Opening marker: cannot be followed by whitespace.
						# Cannot be followed by Unicode category Zs, Pe, Pf.
						(?(R)([*]{1,2})|^[*]{2}(?![\s\p{Zs}\p{Pe}\p{Pf}]))
						# Capture two or more matched backticks (code span?),
						# escaped marker, other char, or recurse the pattern:
						((
						(?>(`{2,})(?!`)(?:[^`]|\4`+|(?!\4)`+)+\4(?!`)|\\\\.|[^*])+|(?R)
						)+?)
						# Closing marker: cannot be preceded by whitespace.
						# Cannot be preceded by Unicode category Zs, Ps, Pi.
						(?(R)\1|(?<![\s\p{Zs}\p{Ps}\p{Pi}])[*]{2})/usx',
					$markdown,
					$matches
				)
				|| $marker === '_'
				&& preg_match(
					# Marker must be preceded by a non-word then 0+ delimeters.
					'/(^|\W|\b_+)$/u', $preceding
				)
				&& preg_match(
					'/
						# Opening marker: cannot be followed by whitespace.
						# Cannot be followed by Unicode category Zs, Pe, Pf.
						(?(R)(_{1,2})|^__(?![\s\p{Zs}\p{Pe}\p{Pf}]))
						# Capture two or more matched backticks (code span?),
						# escaped marker, other char, or recurse the pattern:
						((
						(?>(`{2,})(?!`)(?:[^`]|\4`+|(?!\4)`+)+\4(?!`)|\\\\.|[^_])+|(?R)
						)+?)
						# Closing marker: cannot be preceded by whitespace.
						# Cannot be preceded by Unicode category Zs, Ps, Pi.
						# Must be followed by 0+ delimeters then a non-word.
						(?(R)\1|(?<![\s\p{Zs}\p{Ps}\p{Pi}])__(?=_*\b))/usx',
					$markdown,
					$matches
				)
			) {
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
							$this->parseInline($matches[2]),
						],
						strlen($matches[0])
					];
				}
			}
		} elseif (
			// Closing marker?
			strpos($markdown, $marker, 1) !== false
		) {
		// Emphasis
			if (
				$marker === '*'
				&& preg_match(
					'/
						# Opening marker: cannot be followed by whitespace.
						# Cannot be followed by Unicode category Zs, Pe, Pf.
						(?(R)([*]{1,2})|^[*](?![\s\p{Zs}\p{Pe}\p{Pf}]))
						# Capture two or more matched backticks (code span?),
						# escaped marker, other char, or recurse the pattern:
						((
						(?>(`{2,})(?!`)(?:[^`]|\4`+|(?!\4)`+)+\4(?!`)|\\\\.|[^*])+|(?R)
						)+?)
						# Closing marker: cannot be preceded by whitespace.
						# Cannot be preceded by Unicode category Zs, Ps, Pi.
						# Emphasis closing marker cannot form a strong marker.
						(?(R)\1|(?<![\s\p{Zs}\p{Ps}\p{Pi}])[*](?![*][^*]))/usx',
					$markdown,
					$matches
				)
				|| $marker === '_'
				&& preg_match(
					# Marker must be preceded by a non-word then 0+ delimeters.
					'/(^|\W|\b_+)$/u', $preceding
				)
				&& preg_match(
					'/
						# Opening marker: cannot be followed by whitespace.
						# Cannot be followed by Unicode category Zs, Pe, Pf.
						(?(R)(_{1,2})|^_(?![\s\p{Zs}\p{Pe}\p{Pf}]))
						# Capture two or more matched backticks (code span?),
						# escaped marker, other char, or recurse the pattern:
						((
						(?>(`{2,})(?!`)(?:[^`]|\4`+|(?!\4)`+)+\4(?!`)|\\\\.|[^_])+|(?R)
						)+?)
						# Closing marker: cannot be preceded by whitespace.
						# Cannot be preceded by Unicode category Zs, Ps, Pi.
						# Must be followed by 0+ delimeters then a non-word.
						(?(R)\1|(?<![\s\p{Zs}\p{Ps}\p{Pi}])_(?=_*\b))/usx',
					$markdown,
					$matches
				)
			) {
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
							$this->parseInline($matches[2]),
						],
						strlen($matches[0])
					];
				}
			}
		}

		return [['text', $markdown[0]], 1];
	}

	protected function renderStrong(
		$block
	): string {
		return '<strong>'
			. $this->renderAbsy($block[1])
			. '</strong>';
	}

	protected function renderEmph(
		$block
	): string {
		return '<em>'
			. $this->renderAbsy($block[1])
			. '</em>';
	}

	abstract protected function detectInlineOverrun(
		$text,
		$length,
		$elements
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
}
