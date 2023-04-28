<?php
/**
 * @copyright Copyright (c) 2023 Daniel Pimley
 * @license https://github.com/cebe/markdown/blob/master/LICENSE
 * @link https://github.com/cebe/markdown#readme
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
	protected function identifyFigure($line)
	{
		return $line[0] === ':' && (!isset($line[1]) || ($l1 = $line[1]) === ' ' || $l1 === "\t");
	}

	/**
	 * Consume lines for a figure element
	 */
	protected function consumeFigure($lines, $current)
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
					// allow contiguous caption lines only
					if (empty($caption) || isset($caption[$i-1])) {
						$caption[$i] = $line = substr($line, 3);
						continue;
					}
				}
				$content[] = $line;
			} else {
				break;
			}
		}

		// Add a figcaption discovered at top or bottom of the figure
		if (!empty($caption) && (isset($caption[$current]) or isset($caption[$i-1]))) {
			if (isset($caption[$current])) {
				$content = array_merge(array("<figcaption>"), $caption, array("</figcaption>"), $content);
			} else {
				$content = array_merge($content, array("<figcaption>"), $caption, array("</figcaption>"));
			}
		}

		$block = [
			'figure',
			'content' => $this->parseBlocks($content),
		];
		return [$block, $i];
	}


	/**
	 * Renders a figure
	 */
	protected function renderFigure($block)
	{
		return '<figure>' . $this->renderAbsy($block['content']) . "</figure>\n";
	}

	abstract protected function parseBlocks($lines);
	abstract protected function renderAbsy($absy);
}
