<?php
/**
 * @copyright Copyright (c) 2014 Carsten Brandt, 2024 Daniel Pimley
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
	 */
	protected function parseEmphStrong($text): array
	{
		$marker = $text[0];

		if (!isset($text[1])) {
			return [['text', $text[0]], 1];
		}

		if ($marker == $text[1]) {
		// Strong.
			// Avoid excessive regex backtracking if there is no closing marker.
			if (strpos($text, $marker . $marker, 2) === false) {
				return [['text', $text[0]], 1];
			}
			if (
				$marker === '*'
				&& preg_match(
					'/^[*]{2}((?>\\\\[*]|[^*]|[*][^*]*[*])+?)[*]{2}/s',
					$text,
					$matches
				)
				|| $marker === '_'
				&& preg_match(
					'/^__((?>\\\\_|[^_]|_[^_]*_)+?)__\b/us',
					$text,
					$matches
				)
			) {
				$content = $matches[1];
				// If nothing is contained in a strong,
				// do not consider it valid.
				if ($content === '') {
					return [['text', $text[0]], 2];
				}
				// First and last chars of the strong text
				// cannot be whitespace.
				if (
					strspn($content, " \t\n", 0, 1) === 0
					&& strspn($content, " \t\n", -1) === 0
				) {
					return [
						[
							'strong',
							$this->parseInline($content),
						],
						strlen($matches[0])
					];
				}
			}
		} else {
		// Emphasis
			// Avoid excessive regex backtracking if there is no closing marker.
			if (strpos($text, $marker, 1) === false) {
				return [['text', $text[0]], 1];
			}
			if (
				$marker === '*'
				&& preg_match(
					'/^[*]((?>\\\\[*]|[^*]|[*][*][^*]+?[*][*])+?)[*](?![*][^*])/s',
					$text,
					$matches
				)
				|| $marker === '_'
				&& preg_match(
					'/^_((?>\\\\_|[^_]|__[^_]*__)+?)_(?!_[^_])\b/us',
					$text,
					$matches
				)
			) {
				$content = $matches[1];
				// If nothing is contained in an emphasis,
				// do not consider it valid.
				if ($content === '') {
					return [['text', $text[0]], 2];
				}
				// First and last chars of the emphasised text
				// cannot be whitespace.
				if (
					strspn($content, " \t\n", 0, 1) === 0
					&& strspn($content, " \t\n", -1) === 0
				) {
					return [
						[
							'emph',
							$this->parseInline($content),
						],
						strlen($matches[0])
					];
				}
			}
		}

		return [['text', $text[0]], 1];
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

	abstract protected function parseInline($text);
	abstract protected function renderAbsy($blocks);
}
