<?php
/**
 * @copyright Copyright 2023 Daniel Pimley
 * @license https://github.com/cebe/markdown/blob/master/LICENSE
 * @link https://github.com/cebe/markdown#readme
 */

namespace cebe\markdown\inline;

/**
 * Adds cite inline elements
 */
trait CiteTrait
{
	protected function parseCiteMarkers()
	{
		return array('*_');
	}

	/**
	 * Parses the strikethrough feature.
	 * @marker *_
	 */
	protected function parseCite($markdown)
	{
		if (preg_match('/^\*_(.+?)_\*/', $markdown, $matches)) {
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

	protected function renderCite($block)
	{
		return '<cite>' . $this->renderAbsy($block[1]) . '</cite>';
	}

	abstract protected function parseInline($text);
	abstract protected function renderAbsy($blocks);
}
