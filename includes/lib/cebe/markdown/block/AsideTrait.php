<?php
/**
 * @copyright Copyright 2023 Daniel Pimley
 * @license https://github.com/xenocrat/chyrp-markdown/blob/master/LICENSE
 * @link https://github.com/xenocrat/chyrp-markdown#readme
 */

namespace cebe\markdown\block;

/**
 * Adds the aside elements
 */
trait AsideTrait
{
	/**
	 * identify a line as the beginning of an aside.
	 */
	protected function identifyAside($line): bool
	{
		return $line[0] === '<' && (!isset($line[1]) || ($l1 = $line[1]) === ' ');
	}

	/**
	 * Consume lines for an aside element
	 */
	protected function consumeAside($lines, $current): array
	{
		// consume until newline
		$content = [];
		for ($i = $current, $count = count($lines); $i < $count; $i++) {
			$line = $lines[$i];
			if (ltrim($line) !== '') {
				if ($line[0] == '<' && !isset($line[1])) {
					$line = '';
				} elseif (strncmp($line, '< ', 2) === 0) {
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
	 * Renders an aside
	 */
	protected function renderAside($block): string
	{
		return '<aside>' . $this->renderAbsy($block['content']) . "</aside>\n";
	}

	abstract protected function parseBlocks($lines);
	abstract protected function renderAbsy($absy);
}
