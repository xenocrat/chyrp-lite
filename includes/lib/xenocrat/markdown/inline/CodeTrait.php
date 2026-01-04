<?php
/**
 * @copyright Copyright 2014 Carsten Brandt, 2024-2026 Daniel Pimley
 * @license https://github.com/xenocrat/chyrp-markdown/blob/master/LICENSE
 * @link https://github.com/xenocrat/chyrp-markdown#readme
 */

namespace xenocrat\markdown\inline;

/**
 * Adds inline code elements.
 */
trait CodeTrait
{
	protected function parseInlineCodeMarkers(): array
	{
		return array('`');
	}

	/**
	 * Parses an inline code span.
	 *
	 * @marker `
	 */
	protected function parseInlineCode($text): array
	{
		if (
			preg_match(
				'/^(`+)(?!`)(.*?[^`])\1(?!`)/s',
				$text,
				$matches
			)
		) {
			$code = str_replace("\n", ' ', $matches[2]);
			if (
				strlen($code) > 2
				&& ltrim($code, ' ') !== ''
				&& substr($code, 0, 1) === ' '
				&& substr($code, -1) === ' '
			) {
				$code = substr($code, 1, -1);
			}
			return [
				[
					'inlineCode',
					$code,
				],
				strlen($matches[0])
			];
		}
		return [['text', $text[0]], 1];
	}

	protected function renderInlineCode($block): string
	{
		if (in_array('table', $this->context)) {
			// Unescape pipes if inside a table cell.
			$block[1] = str_replace('\|', '|', $block[1]);
		}
		return '<code>'
			. $this->escapeHtmlEntities(
				$block[1],
				ENT_COMPAT | ENT_SUBSTITUTE | ENT_DISALLOWED
			)
			. '</code>';
	}

	abstract protected function renderText($block);
	abstract protected function escapeHtmlEntities($text, $flags = 0);
}
