<?php
/**
 * @copyright Copyright (c) 2014 Carsten Brandt
 * @license https://github.com/cebe/markdown/blob/master/LICENSE
 * @link https://github.com/cebe/markdown#readme
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
	protected function identifyAside($line)
	{
		return $line[0] === '<' && (!isset($line[1]) || ($l1 = $line[1]) === ' ' || $l1 === "\t");
	}

	/**
	 * Consume lines for an aside element
	 */
	protected function consumeAside($lines, $current)
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
	protected function renderAside($block)
	{
		return '<aside>' . $this->renderAbsy($block['content']) . "</aside>\n";
	}

	abstract protected function parseBlocks($lines);
	abstract protected function renderAbsy($absy);
}
