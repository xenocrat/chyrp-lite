<?php
/**
 * @copyright Copyright (c) 2014 Carsten Brandt, 2024 Daniel Pimley
 * @license https://github.com/xenocrat/chyrp-markdown/blob/master/LICENSE
 * @link https://github.com/xenocrat/chyrp-markdown#readme
 */

namespace cebe\markdown\block;

/**
 * Adds headline blocks.
 */
trait HeadlineTrait
{
	/**
	 * Bust the alphabetical calling strategy.
	 */
	protected function identifyHeadlinePriority(): string
	{
		return 'zHeadline';
	}

	/**
	 * Identify a line as a headline.
	 */
	protected function identifyHeadline($line, $lines, $current): bool
	{
		return (
			// ATX headline
			preg_match('/^ {0,3}(#{1,6})([ \t]|$)/', $line)
			// setext headline
			|| !empty($lines[$current + 1])
			&& preg_match('/^ {0,3}(\-+|=+)\s*$/', $lines[$current + 1])
		);
	}

	/**
	 * Consume lines for a headline.
	 */
	protected function consumeHeadline($lines, $current): array
	{
		if (
			preg_match(
				'/^ {0,3}(#{1,6})([ \t]|$)/',
				$lines[$current],
				$matches
			)
		) {
			// ATX headline
			$line = ltrim($lines[$current], "# \t");
			$line = preg_replace('/ +(#+ *)?$/', '', $line);
			$block = [
				'headline',
				'content' => $this->parseInline($line),
				'level' => strlen($matches[1]),
			];
			return [$block, $current];
		} else {
			// setext headline
			$line = trim($lines[$current]);
			$block = [
				'headline',
				'content' => $this->parseInline($line),
				'level' => substr_count($lines[$current + 1], '=') ? 1 : 2,
			];
			return [$block, $current + 1];
		}
	}

	/**
	 * Renders a headline.
	 */
	protected function renderHeadline($block): string
	{
		$tag = 'h' . $block['level'];
		return "<$tag>"
			. $this->renderAbsy($block['content'])
			. "</$tag>\n";
	}

	abstract protected function parseInline($text);
	abstract protected function renderAbsy($absy);
}
