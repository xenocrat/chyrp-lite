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
	protected function parseMath($text): array
	{
		if (
			preg_match(
				'/^\$`(.*?[^\\\\])`\$/s',
				str_replace(
					"\\\\",
					"\\\\".chr(31),
					$text
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
			$math = str_replace("\n", ' ', $matches[1]);
			if (
				strlen($math) > 2
				&& ltrim($math, ' ') !== ''
				&& substr($math, 0, 1) === ' '
				&& substr($math, -1) === ' '
			) {
				$math = substr($math, 1, -1);
			}
			return [
				[
					'inlineMath',
					$math
				],
				strlen($matches[0])
			];
		}
		return [['text', $text[0] . $text[1]], 2];
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
