<?php
/**
 * @copyright Copyright 2023-2026 Daniel Pimley
 * @license https://github.com/xenocrat/chyrp-markdown/blob/master/LICENSE
 * @link https://github.com/xenocrat/chyrp-markdown#readme
 */

namespace xenocrat\markdown\inline;

/**
 * Adds cite inline elements.
 */
trait CiteTrait
{
	protected function parseCiteMarkers(): array
	{
		return array('*_');
	}

	/**
	 * Parses the cite feature.
	 *
	 * @marker *_
	 */
	protected function parseCite($markdown): array
	{
		if (
			preg_match(
				'/^\*_(.*?[^\\\\])_\*/s',
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
			$matches[1] = str_replace(
				"\\\\".chr(31),
				"\\\\",
				$matches[1]
			);
			return [
				[
					'cite',
					$this->parseInline($matches[1])
				],
				strlen($matches[0])
			];
		}
		return [['text', $markdown[0] . $markdown[1]], 2];
	}

	protected function renderCite($block): string
	{
		return '<cite>'
			. $this->renderAbsy($block[1])
			. '</cite>';
	}

	abstract protected function renderText($block);
	abstract protected function parseInline($text);
	abstract protected function renderAbsy($blocks);
}
