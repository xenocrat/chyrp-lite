<?php
/**
 * @copyright Copyright (c) 2014 Carsten Brandt, 2024 Daniel Pimley
 * @license https://github.com/xenocrat/chyrp-markdown/blob/master/LICENSE
 * @link https://github.com/xenocrat/chyrp-markdown#readme
 */

namespace xenocrat\markdown\block;

/**
 * Adds horizontal rules.
 */
trait RuleTrait
{
	/**
	 * Bust the alphabetical calling strategy.
	 */
	protected function identifyHrPriority(): string
	{
		return 'aHr';
	}

	/**
	 * Identify a line as a horizontal rule.
	 */
	protected function identifyHr($line): bool
	{
		// at least 3 of -, * or _ on one line make a hr
		return preg_match('/^ {0,3}([\-\*_])\s*\1\s*\1(\1|\s)*$/', $line);
	}

	/**
	 * Consume a horizontal rule.
	 */
	protected function consumeHr($lines, $current): array
	{
		return [['hr'], $current];
	}

	/**
	 * Renders a horizontal rule.
	 */
	protected function renderHr($block): string
	{
		return $this->html5 ? "<hr>\n" : "<hr />\n";
	}

} 