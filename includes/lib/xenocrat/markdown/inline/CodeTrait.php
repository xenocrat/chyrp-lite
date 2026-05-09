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
	protected function parseInlineCode($markdown): array
	{
		if (
			preg_match(
				'/^
					# Opening marker:
					(`+)
					# First char cannot be a delimiter.
					(?!`)
					# Final capture char cannot be a delimiter:
					(.*?[^`])
					# Closing marker:
					\1
					# Next char must not be a delimiter.
					(?!`)/sx',
				$markdown,
				$matches
			)
		) {
			$code = str_replace("\n", ' ', $matches[2]);
			if (
				strlen($code) > 2
				&& ltrim($code, ' ') !== ''
				&& str_starts_with($code, ' ')
				&& str_ends_with($code, ' ')
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
		$spn = strspn($markdown, '`') ?: 1;
		return [
			[
				'text',
				str_repeat($markdown[0], $spn)
			],
			$spn
		];
	}

	protected function renderInlineCode($block): string
	{
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
