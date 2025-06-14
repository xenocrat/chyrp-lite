<?php
/**
 * @copyright Copyright 2014 Carsten Brandt, 2024 Daniel Pimley
 * @license https://github.com/xenocrat/chyrp-markdown/blob/master/LICENSE
 * @link https://github.com/xenocrat/chyrp-markdown#readme
 */

namespace xenocrat\markdown\inline;

/**
 * Adds strikeout inline elements.
 */
trait StrikeoutTrait
{
	protected function parseStrikeMarkers(): array
	{
		return array('~');
	}

	/**
	 * Parses the strikethrough feature.
	 *
	 * @marker ~
	 */
	protected function parseStrike($markdown): array
	{
		if (
			preg_match(
				'/^(~{1,2})(?!~)(.*?([^~\\\\]|(?<=\\\\)~))\1(?!~)/s',
				str_replace(
					"\\\\",
					"\\\\".chr(31),
					$markdown
				),
				$matches
			)
		) {
			$matches[0] = str_replace(
				"\\\\".chr(31),
				"\\\\",
				$matches[0]
			);
			$matches[2] = str_replace(
				"\\\\".chr(31),
				"\\\\",
				$matches[2]
			);
			return [
				[
					'strike',
					$this->parseInline($matches[2])
				],
				strlen($matches[0])
			];
		}
		return [['text', $markdown[0]], 1];
	}

	protected function renderStrike($block): string
	{
		return '<del>'
			. $this->renderAbsy($block[1])
			. '</del>';
	}

	abstract protected function parseInline($text);
	abstract protected function renderAbsy($blocks);
}
