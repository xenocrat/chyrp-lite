<?php
/**
 * @copyright Copyright (c) 2014 Carsten Brandt, 2024 Daniel Pimley
 * @license https://github.com/xenocrat/chyrp-markdown/blob/master/LICENSE
 * @link https://github.com/xenocrat/chyrp-markdown#readme
 */

namespace cebe\markdown\inline;

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
		// strong
			// avoid excessive regex backtracking if there is no closing marker
			if (strpos($text, $marker . $marker, 2) === false) {
				return [['text', $text[0] . $text[1]], 2];
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
					'/^__((?>\\\\_|[^_]|_[^_]*_)+?)__/us',
					$text,
					$matches
				)
			) {
				$content = $matches[1];
				// first and last chars must be graphical
				if (
					ctype_graph($content[0])
					&& ctype_graph(substr($content, -1))
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
		// emph
			// avoid excessive regex backtracking if there is no closing marker
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
				// if only a single whitespace or nothing is contained in an emphasis,
				// do not consider it valid
				if ($matches[1] === '' || $matches[1] === ' ') {
					return [['text', $text[0]], 1];
				}
				$content = $matches[1];
				// first and last chars must be graphical
				if (
					ctype_graph($content[0])
					&& ctype_graph(substr($content, -1))
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
