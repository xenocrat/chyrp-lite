<?php
/**
 * @copyright Copyright 2023 Daniel Pimley
 * @license https://github.com/xenocrat/chyrp-markdown/blob/master/LICENSE
 * @link https://github.com/xenocrat/chyrp-markdown#readme
 */

namespace cebe\markdown\inline;

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
	 * @marker *_
	 */
	protected function parseCite($markdown): array
	{
		if (preg_match('/^\*_(.*?[^\\\\])_\*/s', $markdown, $matches)) {
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

	abstract protected function parseInline($text);
	abstract protected function renderAbsy($blocks);
}
