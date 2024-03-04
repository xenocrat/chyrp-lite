<?php
/**
 * @copyright Copyright (c) 2014 Carsten Brandt
 * @license https://github.com/xenocrat/chyrp-markdown/blob/master/LICENSE
 * @link https://github.com/xenocrat/chyrp-markdown#readme
 */

namespace cebe\markdown\block;

/**
 * Adds the block quote elements
 */
trait QuoteTrait
{
	/**
	 * identify a line as the beginning of a block quote.
	 */
	protected function identifyQuote($line): bool
	{
		return $line[0] === '>' && (!isset($line[1]) || ($l1 = $line[1]) === ' ');
	}

	/**
	 * Consume lines for a blockquote element
	 */
	protected function consumeQuote($lines, $current): array
	{
		// consume until end of markers
		$content = [];
		for ($i = $current, $count = count($lines); $i < $count; $i++) {
			$line = $lines[$i];
			if (ltrim($line) !== '') {
				if ($line[0] == '>' && !isset($line[1])) {
					$line = '';
				} elseif (strncmp($line, '> ', 2) === 0) {
					$line = substr($line, 2);
				} else {
					--$i;
					break;
				}
				$content[] = $line;
			} else {
				break;
			}
		}

		$block = [
			'quote',
			'content' => $this->parseBlocks($content),
		];
		return [$block, $i];
	}

	/**
	 * Renders a blockquote
	 */
	protected function renderQuote($block): string
	{
		return '<blockquote>' . $this->renderAbsy($block['content']) . "</blockquote>\n";
	}

	abstract protected function parseBlocks($lines);
	abstract protected function renderAbsy($absy);
}
