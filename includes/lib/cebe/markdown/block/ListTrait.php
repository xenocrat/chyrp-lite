<?php
/**
 * @copyright Copyright (c) 2014 Carsten Brandt
 * @license https://github.com/xenocrat/chyrp-markdown/blob/master/LICENSE
 * @link https://github.com/xenocrat/chyrp-markdown#readme
 */

namespace cebe\markdown\block;

/**
 * Adds the list blocks
 */
trait ListTrait
{
	/**
	 * @var bool enable support for a `start` attribute of ordered lists. This means that lists
	 * will start with the number you actually type in markdown and not the HTML generated one.
	 * Defaults to `false` which means that numeration of all ordered lists(<ol>) starts with 1.
	 */
	public $keepListStartNumber = false;

	/**
	 * Bust the alphabetical calling strategy.
	 */
	protected function identifyUlPriority(): string
	{
		return 'bUl';
	}

	/**
	 * identify a line as the beginning of an ordered list.
	 */
	protected function identifyOl($line): bool
	{
		return preg_match('/^ {0,3}\d+[\.\)]([ \t]|$)/', $line);
	}

	/**
	 * identify a line as the beginning of an unordered list.
	 */
	protected function identifyUl($line): bool
	{
		return preg_match('/^ {0,3}[\-\+\*]([ \t]|$)/', $line);
	}

	/**
	 * Consume lines for an ordered list
	 */
	protected function consumeOl($lines, $current): array
	{
		// consume until newline or end condition

		$block = [
			'list',
			'list' => 'ol',
			'attr' => [],
			'items' => [],
		];
		return $this->consumeList($lines, $current, $block, 'ol');
	}

	/**
	 * Consume lines for an unordered list
	 */
	protected function consumeUl($lines, $current): array
	{
		// consume until newline or end condition

		$block = [
			'list',
			'list' => 'ul',
			'items' => [],
		];
		return $this->consumeList($lines, $current, $block, 'ul');
	}

	private function consumeList($lines, $current, $block, $type): array
	{
		$item = 0;
		$mw = 0;
		$lastLineEmpty = false;
		for ($i = $current, $count = count($lines); $i < $count; $i++) {
			$line = $lines[$i];
			// match a list marker on the beginning of the line
			$pattern = ($type === 'ol') ?
				'/^( {0,3})(\d+)([\.\)])([ \t]+|$)/' :
				'/^( {0,3})([\-\+\*])([ \t]+|$)/';
			// if not the first item, marker indentation must be less than
			// width of preceeding marker - otherwise it is a continuation
			// of the current item containing a sub-list
			if (preg_match($pattern, $line, $matches)
				&& ($i === $current || strlen($matches[1]) < $mw)) {
				if ($i === $current) {
					// first item - store the marker for comparison
					$marker = $type === 'ol' ? $matches[3] : $matches[2];
					// store the ol start number
					if ($type === 'ol' && $this->keepListStartNumber) {
						// attr `start` for ol
						$block['attr']['start'] = $matches[2];
					}
				} else {
					$newMarker = $type === 'ol' ? $matches[3] : $matches[2];
					// marker has changed: end of list
					if (strcmp($marker, $newMarker) !== 0) {
						--$i;
						break;
					}
				}

				// store the marker width
				$mw = strlen($matches[0]);
				$line = substr($line, $mw);
				$block['items'][++$item][] = $line;
				$block['looseItems'][$item] = $lastLineEmpty;
				$lastLineEmpty = false;
			} elseif (ltrim($line) === '') {
				// line is blank: may be a loose list
				$lastLineEmpty = true;

				// no more lines: end of list
				if (!isset($lines[$i + 1])) {
					break;

				// next line is also blank
				} elseif ($lines[$i + 1] === '' || ltrim($lines[$i + 1]) === '') {
					$block['items'][$item][] = $line;

				// next line is indented enough to continue this item
				} elseif (ctype_space(substr($lines[$i + 1], 0, $mw))) {
					$block['items'][$item][] = $line;

				// next line starts the next item in this list
				// -> loose list
				} elseif (preg_match($pattern, $lines[$i + 1])) {
					$block['items'][$item][] = $line;
					$block['looseItems'][$item] = true;

				// everything else ends the list
				} else {
					break;
				}
			} else {
				// line is not indented enough to continue this item
				if (strlen($line) < $mw || !ctype_space(substr($line, 0, $mw))) {
					--$i;
					break;
				}
				$line = substr($line, $mw);
				$block['items'][$item][] = $line;
				$lastLineEmpty = false;
			}
		}

		foreach ($block['items'] as $itemId => $itemLines) {
			$content = [];
			if (!$block['looseItems'][$itemId]) {
				$firstPar = [];
				while (!empty($itemLines)
					&& rtrim($itemLines[0]) !== ''
					&& $this->detectLineType($itemLines, 0) === 'paragraph') {
					$firstPar[] = array_shift($itemLines);
				}
				$content = $this->parseInline(implode("\n", $firstPar));
			}
			if (!empty($itemLines)) {
				$content = array_merge($content, $this->parseBlocks($itemLines));
			}
			$block['items'][$itemId] = $content;
		}

		return [$block, $i];
	}

	/**
	 * Renders a list
	 */
	protected function renderList($block): string
	{
		$type = $block['list'];

		if (!empty($block['attr'])) {
			$output = "<$type " . $this->generateHtmlAttributes($block['attr']) . ">\n";
		} else {
			$output = "<$type>\n";
		}

		foreach ($block['items'] as $item => $itemLines) {
			$output .= '<li>' . $this->renderAbsy($itemLines). "</li>\n";
		}
		return $output . "</$type>\n";
	}


	/**
	 * Return html attributes string from [attrName => attrValue] list
	 * @param array $attributes the attribute name-value pairs.
	 * @return string
	 */
	private function generateHtmlAttributes($attributes): string
	{
		foreach ($attributes as $name => $value) {
			$attributes[$name] = "$name=\"$value\"";
		}
		return implode(' ', $attributes);
	}

	abstract protected function parseBlocks($lines);
	abstract protected function parseInline($text);
	abstract protected function renderAbsy($absy);
	abstract protected function detectLineType($lines, $current);
}
