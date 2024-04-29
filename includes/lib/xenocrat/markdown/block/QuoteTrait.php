<?php
/**
 * @copyright Copyright (c) 2014 Carsten Brandt, 2024 Daniel Pimley
 * @license https://github.com/xenocrat/chyrp-markdown/blob/master/LICENSE
 * @link https://github.com/xenocrat/chyrp-markdown#readme
 */

namespace xenocrat\markdown\block;

/**
 * Adds blockquote blocks.
 */
trait QuoteTrait
{
	/**
	 * Identify a line as the beginning of a blockquote.
	 */
	protected function identifyQuote($line): bool
	{
		if (
			$line[0] === ' '
			&& strspn($line, ' ') < 4
		) {
		// trim up to three spaces
			$line = ltrim($line, ' ');
		}
		return $line[0] === '>';
	}

	/**
	 * Consume lines for a blockquote element.
	 */
	protected function consumeQuote($lines, $current): array
	{
		$content = [];

		// consume until end of markers...
		for ($i = $current, $count = count($lines); $i < $count; $i++) {
			$line = $lines[$i];
			if (
				isset($line[0])
				&& $line[0] === ' '
				&& strspn($line, ' ') < 4
			) {
			// Trim up to three spaces.
				$line = ltrim($line, ' ');
			}
			if (ltrim($line) !== '') {
				if ($line[0] == '>' && !isset($line[1])) {
					$line = '';
				} elseif (str_starts_with($line, '> ')) {
					$line = substr($line, 2);
				} elseif (str_starts_with($line, '>')) {
					$line = substr($line, 1);
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
	 * Renders a blockquote.
	 */
	protected function renderQuote($block): string
	{
		return "<blockquote>\n"
		. $this->renderAbsy($block['content'])
		. "</blockquote>\n";
	}

	abstract protected function parseBlocks($lines);
	abstract protected function renderAbsy($absy);
}
