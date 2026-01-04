<?php
/**
 * @copyright Copyright 2023-2026 Daniel Pimley
 * @license https://github.com/xenocrat/chyrp-markdown/blob/master/LICENSE
 * @link https://github.com/xenocrat/chyrp-markdown#readme
 */

namespace xenocrat\markdown\inline;

/**
 * Adds highlight inline elements.
 */
trait HighlightTrait
{
	protected function parseHighlightMarkers(): array
	{
		return array('==');
	}

	/**
	 * Parses the highlight feature.
	 *
	 * @marker ==
	 */
	protected function parseHighlight($markdown): array
	{
		if (
			preg_match(
				'/^==(?!=)(.*?([^=\\\\]|(?<=\\\\)=))==(?!=)/s',
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
					'highlight',
					$this->parseInline($matches[1])
				],
				strlen($matches[0])
			];
		}
		return [['text', $markdown[0] . $markdown[1]], 2];
	}

	protected function renderHighlight($block): string
	{
		return '<mark>'
			. $this->renderAbsy($block[1])
			. '</mark>';
	}

	abstract protected function renderText($block);
	abstract protected function parseInline($text);
	abstract protected function renderAbsy($blocks);
}
