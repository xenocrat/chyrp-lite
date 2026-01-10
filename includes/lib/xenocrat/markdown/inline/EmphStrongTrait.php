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
	 */
	protected function parseEmphStrong($markdown): array
	{
		$marker = $markdown[0];

		if (!isset($markdown[1])) {
			return [['text', $markdown[0]], 1];
		}

		if ($marker == $markdown[1]) {
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
					'/^[*]{2}((?>\\\\[*]|[^*]|[*][^*]*[*])+?)[*]{2}/s',
					$regexable,
					$matches
				)
				|| $marker === '_'
				&& preg_match(
					'/^__((?>\\\\_|[^_]|_[^_]*_)+?)__\b/us',
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
				$content = $matches[1];
				// Strong cannot be empty.
				// First char cannot be a space separator, close or final punctuation.
				// Last char cannot be a space separator, open or initial punctuation.
				if (
					$content !== ''
					&& preg_match(
						'/^(?![\s\p{Zs}\p{Pe}\p{Pf}]).+(?<![\s\p{Zs}\p{Ps}\p{Pi}])$/us',
						$content
					)
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
					'/^[*]((?>\\\\[*]|[^*]|[*][*][^*]+?[*][*])+?)[*](?![*][^*])/s',
					$regexable,
					$matches
				)
				|| $marker === '_'
				&& preg_match(
					'/^_((?>\\\\_|[^_]|__[^_]*__)+?)_(?!_[^_])\b/us',
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
				$content = $matches[1];
				// Emphasis cannot be empty.
				// First char cannot be a space separator, close or final punctuation.
				// Last char cannot be a space separator, open or initial punctuation.
				if (
					$content !== ''
					&& preg_match(
						'/^(?![\s\p{Zs}\p{Pe}\p{Pf}]).+(?<![\s\p{Zs}\p{Ps}\p{Pi}])$/us',
						$content
					)
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

	abstract protected function renderText($block);
	abstract protected function parseInline($text);
	abstract protected function renderAbsy($blocks);
}
