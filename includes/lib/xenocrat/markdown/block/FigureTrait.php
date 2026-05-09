<?php
/**
 * @copyright Copyright 2023-2026 Daniel Pimley
 * @license https://github.com/xenocrat/chyrp-markdown/blob/master/LICENSE
 * @link https://github.com/xenocrat/chyrp-markdown#readme
 */

namespace xenocrat\markdown\block;

/**
 * Adds figure/figcaption blocks.
 */
trait FigureTrait
{
	/**
	 * Identify a line as the beginning of a figure.
	 */
	protected function identifyFigure($line): bool
	{
		if (
			$line[0] === ' '
			&& strspn($line, ' ') < 4
		) {
		// Trim up to three spaces.
			$line = ltrim($line, ' ');
		}
		return $line[0] === ':';
	}

	/**
	 * Consume lines for a figure.
	 */
	protected function consumeFigure($lines, $current): array
	{
		$content = [];
		$caption = [];

		// Consume until end of markers...
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
				if (str_starts_with($line, ':: ')) {
					$caption[$i] = substr($line, 3);
				} elseif (str_starts_with($line, '::')) {
					$caption[$i] = substr($line, 2);
				} elseif (str_starts_with($line, ': ')) {
					$content[$i] = substr($line, 2);
				} elseif (str_starts_with($line, ':')) {
					$content[$i] = substr($line, 1);
				} else {
					--$i;
					break;
				}
			} else {
				break;
			}
		}

		$kf = array_key_first($content);
		$kl = array_key_last($content);

		// Decide caption placement and remove invalid lines.
		if (isset($caption[$current])) {
			$endcap = false;
			foreach ($caption as $key => $value) {
				if ($key > $kf) {
					unset($caption[$key]);
				}
			}
		} elseif (isset($caption[$kl + 1])) {
			$endcap = true;
			foreach ($caption as $key => $value) {
				if ($key < $kl) {
					unset($caption[$key]);
				}
			}
		} else {
			$endcap = null;
			$caption = [];
		}

		$block = [
			'figure',
			'endcap' => $endcap,
			'content' => $this->parseBlocks(array_values($content)),
			'caption' => $this->parseBlocks(array_values($caption)),
		];

		return [$block, $i];
	}

	/**
	 * Renders a figure.
	 */
	protected function renderFigure($block): string
	{
		$caption = $block['endcap'] === null ?
			'' :
			"<figcaption>\n"
				. $this->renderAbsy($block['caption'])
				. "</figcaption>\n" ;

		if ($block['endcap'] === false) {
			$figure = "<figure>\n"
				. $caption
				. $this->renderAbsy($block['content'])
				. "</figure>\n";
		} else {
			$figure = "<figure>\n"
				. $this->renderAbsy($block['content'])
				. $caption
				. "</figure>\n";
		}

		return $figure;
	}

	abstract protected function parseBlocks($lines);
	abstract protected function renderAbsy($absy);
}
