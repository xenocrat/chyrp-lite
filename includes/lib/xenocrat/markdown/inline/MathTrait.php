<?php
/**
 * @copyright Copyright 2024 Daniel Pimley
 * @license https://github.com/xenocrat/chyrp-markdown/blob/master/LICENSE
 * @link https://github.com/xenocrat/chyrp-markdown#readme
 */

namespace xenocrat\markdown\inline;

/**
 * Adds math expression (LaTeX) inline elements.
 */
trait MathTrait
{
	protected function parseMathMarkers(): array
	{
		return array('$`');
	}

	/**
	 * Parses the math feature.
	 *
	 * @marker $`
	 */
	protected function parseMath($markdown): array
	{
		if (
			preg_match(
				'/^\$`(.*?[^\\\\])`\$/s',
				$markdown,
				$matches
			)
		) {
			return [
				[
					'inlineMath',
					$matches[1]
				],
				strlen($matches[0])
			];
		}
		return [['text', $markdown[0] . $markdown[1]], 2];
	}

	protected function renderInlineMath($block): string
	{
		return '<la-tex display="inline">'
			. $this->escapeHtmlEntities(
				$block[1],
				ENT_COMPAT | ENT_SUBSTITUTE
			)
			. '</la-tex>';
	}

	abstract protected function escapeHtmlEntities($text, $flags = 0);
}
