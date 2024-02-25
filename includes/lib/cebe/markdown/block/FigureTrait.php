<?php
/**
 * @copyright Copyright (c) 2023 Daniel Pimley
 * @license https://github.com/xenocrat/chyrp-markdown/blob/master/LICENSE
 * @link https://github.com/xenocrat/chyrp-markdown#readme
 */

namespace cebe\markdown\block;

/**
 * Adds the figure and figcaption elements
 */
trait FigureTrait
{
	/**
	 * identify a line as the beginning of a figure.
	 */
	protected function identifyFigure($line): bool
	{
		return $line[0] === ':'
			&& (!isset($line[1]) || ($l1 = $line[1]) === ' ' || $l1 === "\t" ||
				($l1 === ":" && (!isset($line[2]) || ($l2 = $line[2]) === ' ' || $l2 === "\t")));
	}

	/**
	 * Consume lines for a figure element
	 */
	protected function consumeFigure($lines, $current): array
	{
		// consume until newline
		$content = [];
		$caption = [];
		for ($i = $current, $count = count($lines); $i < $count; $i++) {
			$line = $lines[$i];
			if (ltrim($line) !== '') {
				if ($line[0] == ':' && !isset($line[1])) {
					$line = '';
				} elseif (strncmp($line, ': ', 2) === 0) {
					$line = substr($line, 2);
				} elseif (strncmp($line, ':: ', 3) === 0) {
					$line = substr($line, 3);
					// allow contiguous caption lines only
					if (empty($caption) || isset($caption[$i-1])) {
						$caption[$i] = $line;
						continue;
					}
				} else {
					--$i;
					break;
				}
				$content[] = $line;
			} else {
				break;
			}
		}

		// determine caption placement
		if (isset($caption[$current])) {
			$endcap = false;
		} elseif (isset($caption[$i-1])) {
			$endcap = true;
		} else {
			$endcap = null;
		}

		$block = [
			'figure',
			'endcap' => $endcap,
			'content' => $this->parseBlocks($content),
			'caption' => $this->parseBlocks(array_values($caption)),
		];
		return [$block, $i];
	}

	/**
	 * Renders a figure
	 */
	protected function renderFigure($block): string
	{
		if ($block['endcap'] !== null) {
			$caption = '<figcaption>' . $this->renderAbsy($block['caption']) . "</figcaption>\n";
		} else {
			$caption = "";
		}

		if ($block['endcap'] === false) {
			$figure = '<figure>' . $caption . $this->renderAbsy($block['content']) . "</figure>\n";
		} else {
			$figure = '<figure>' . $this->renderAbsy($block['content']) . $caption . "</figure>\n";
		}

		return $figure;
	}

	abstract protected function parseBlocks($lines);
	abstract protected function renderAbsy($absy);
}
