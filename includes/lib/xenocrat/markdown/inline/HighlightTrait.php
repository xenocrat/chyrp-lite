<?php
/**
 * @copyright Copyright 2023 Daniel Pimley
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
				'/^==(?!=)(.*?([^=\\\\]|(?<=\\\\)\\\\))==(?!=)/s',
				$markdown,
				$matches
			)
		) {
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

	abstract protected function parseInline($text);
	abstract protected function renderAbsy($blocks);
}
