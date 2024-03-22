<?php
/**
 * @copyright Copyright 2023 Daniel Pimley
 * @license https://github.com/xenocrat/chyrp-markdown/blob/master/LICENSE
 * @link https://github.com/xenocrat/chyrp-markdown#readme
 */

namespace cebe\markdown\block;

/**
 * Adds aside blocks.
 */
trait AsideTrait
{
	/**
	 * Identify a line as the beginning of an aside.
	 */
	protected function identifyAside($line): bool
	{
		if (
			$line[0] === ' '
			&& strspn($line, ' ') < 4
		) {
		// trim up to three spaces
			$line = ltrim($line, ' ');
		}
		return (
			$line[0] === '<'
			&& (!isset($line[1]) || ($l1 = $line[1]) === ' ')
		);
	}

	/**
	 * Consume lines for an aside.
	 */
	protected function consumeAside($lines, $current): array
	{
		$content = [];

		// consume until end of markers
		for ($i = $current, $count = count($lines); $i < $count; $i++) {
			$line = $lines[$i];
			if (
				isset($line[0])
				&& $line[0] === ' '
				&& strspn($line, ' ') < 4
			) {
			// trim up to three spaces
				$line = ltrim($line, ' ');
			}
			if (ltrim($line) !== '') {
				if ($line[0] == '<' && !isset($line[1])) {
					$line = '';
				} elseif (str_starts_with($line, '< ')) {
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
			'aside',
			'content' => $this->parseBlocks($content),
		];

		return [$block, $i];
	}

	/**
	 * Renders an aside.
	 */
	protected function renderAside($block): string
	{
		return '<aside>'
			. $this->renderAbsy($block['content'])
			. "</aside>\n";
	}

	abstract protected function parseBlocks($lines);
	abstract protected function renderAbsy($absy);
}
