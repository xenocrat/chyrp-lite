<?php
/**
 * @copyright Copyright 2024-2026 Daniel Pimley
 * @license https://github.com/xenocrat/chyrp-markdown/blob/master/LICENSE
 * @link https://github.com/xenocrat/chyrp-markdown#readme
 */

namespace xenocrat\markdown\block;

/**
 * Adds math expression (LaTeX) blocks.
 */
trait MathTrait
{
	/**
	 * Identify a line as the beginning of a math expression.
	 */
	protected function identifyMath($line): bool
	{
		if (
			$line[0] === ' '
			&& strspn($line, ' ') < 4
		) {
		// trim up to three spaces
			$line = ltrim($line, ' ');
		}
		return str_starts_with($line, '$$');
	}

	/**
	 * Consume lines for a math expression.
	 */
	protected function consumeMath($lines, $current): array
	{
		$indent = strspn($lines[$current], ' ');
		$line = substr($lines[$current], $indent);
		$mw = strspn($line, $line[0]);
		$fence = substr($line, 0, $mw);
		$content = [];

		// Consume until end fence...
		for ($i = $current + 1, $count = count($lines); $i < $count; $i++) {
			$line = $lines[$i];
			$leadingSpaces = strspn($line, ' ');
			if (
				$leadingSpaces > 3
				|| strspn(ltrim($line), $fence[0]) < $mw
				|| !str_ends_with(rtrim($line), $fence[0])
			) {
				if ($indent > 0 && $leadingSpaces > 0) {
					if ($leadingSpaces < $indent) {
						$line = ltrim($line);
					} else {
						$line = substr($line, $indent);
					}
				}
				$content[] = $line;
			} else {
				break;
			}
		}
		$block = [
			'math',
			'content' => implode("\n", $content),
		];

		return [$block, $i];
	}

	/**
	 * Renders a math expression.
	 */
	protected function renderMath($block): string
	{
		return '<la-tex display="block">'
			. $this->escapeHtmlEntities(
				$block['content'],
				ENT_COMPAT | ENT_SUBSTITUTE | ENT_DISALLOWED
			)
			. ($block['content'] === '' ? '' : "\n" )
			. "</la-tex>\n";
	}

	abstract protected function escapeHtmlEntities($text, $flags = 0);
}
