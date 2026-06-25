<?php
/**
 * @copyright Copyright 2024-2026 Daniel Pimley
 * @license https://github.com/xenocrat/chyrp-markdown/blob/master/LICENSE
 * @link https://github.com/xenocrat/chyrp-markdown#readme
 */

namespace xenocrat\markdown\inline;

/**
 * Adds math expression (LaTeX) inline elements.
 */
trait MathTrait
{
	protected function parseMathMarkers(
	): array {
		return array('$`');
	}

	/**
	 * Parses the math feature.
	 *
	 * @marker $`
	 */
	protected function parseMath(
		$markdown
	): array {
		if (
			preg_match(
				'/^
					# Opening marker:
					\$`
					# Final capture char cannot be a delimiter:
					(.*?[^\\\\])
					# Closing marker:
					`\$/sx',
				str_replace(
					'\\\\',
					'\\\\'.chr(31),
					$markdown
				),
				$matches
			)
		) {
			$matches[0] = str_replace(
				'\\\\'.chr(31),
				'\\\\',
				$matches[0]
			);

			$matches[1] = str_replace(
				'\\\\'.chr(31),
				'\\\\',
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

		return [['text', $markdown[0] . $markdown[1]], 2];
	}

	protected function renderInlineMath(
		$block
	): string {
		return '<la-tex display="inline">'
			. $this->escapeHtmlEntities(
				$block[1],
				ENT_COMPAT | ENT_SUBSTITUTE | ENT_DISALLOWED
			)
			. '</la-tex>';
	}

	abstract protected function escapeHtmlEntities(
		$text,
		$flags = 0
	);

	abstract protected function renderText(
		$block
	);
}
