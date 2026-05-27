<?php
/**
 * @copyright Copyright 2014 Carsten Brandt, 2024-2026 Daniel Pimley
 * @license https://github.com/xenocrat/chyrp-markdown/blob/master/LICENSE
 * @link https://github.com/xenocrat/chyrp-markdown#readme
 */

namespace xenocrat\markdown\block;

/**
 * Adds list blocks.
 */
trait ListTrait
{
	/**
	 * @var bool Enable support for a `start` attribute of ordered lists.
	 *
	 * This means that lists will start with the number defined in the markdown.
	 */
	public $keepListStartNumber = true;

	/**
	 * @var bool Enable support for a `reversed` attribute of ordered lists.
	 *
	 * This means that lists defined in the markdown with a start number
	 * greater than the end number will be rendered with descending numbers.
	 */
	public $keepReversedList = false;

	/**
	 * Identify a line as the beginning of an ordered list.
	 */
	protected function identifyOl($line): bool
	{
		return (
			preg_match('/^ {0,3}(\d{1,9})[\.\)]([ \t]|$)/', $line, $matches)
			&& (
				$matches[1] === '1'
				|| reset($this->context) !== 'consumeParagraph'
			)
		);
	}

	/**
	 * Identify a line as the beginning of an unordered list.
	 */
	protected function identifyUl($line): bool
	{
		return preg_match('/^ {0,3}[\-\+\*]([ \t]|$)/', $line);
	}

	/**
	 * Consume lines for an ordered list.
	 */
	protected function consumeOl($lines, $current): array
	{
		$block = [
			'list',
			'list' => 'ol',
			'attr' => [],
			'items' => [],
			'loose' => false,
		];
		return $this->consumeList($lines, $current, $block, 'ol');
	}

	/**
	 * Consume lines for an unordered list.
	 */
	protected function consumeUl($lines, $current): array
	{
		$block = [
			'list',
			'list' => 'ul',
			'items' => [],
			'loose' => false,
		];
		return $this->consumeList($lines, $current, $block, 'ul');
	}

	private function consumeList($lines, $current, $block, $type): array
	{
		$item = 0;
		$marker = '';
		$mw = 0;
		$nums = [];
		$pad = chr(29);

		// Consume until end condition...
		for ($i = $current, $count = count($lines); $i < $count; $i++) {
			$line = $this->expandTabs($lines[$i], $pad);
			$pattern = ($type === 'ol') ?
				'/^(([ ]{0,3})(\d{1,9})([\.\)]))
					# Match 1 spacing char if there are 5+ (code block?),
					# otherwise match 1-4 spacing chars or end of line.
					([ \x1D](?=[ \x1D]{4})|[ \x1D]{1,4}|$)/x' :
				'/^(([ ]{0,3})([\-\+\*]))
					# Match 1 spacing char if there are 5+ (code block?),
					# otherwise match 1-4 spacing chars or end of line.
					([ \x1D](?=[ \x1D]{4})|[ \x1D]{1,4}|$)/x';

			// If not the first item, marker indentation must be less than
			// width of preceeding marker - otherwise it is a continuation
			// of the current item containing a marker for a sub-list item.
			if (
				preg_match($pattern, $line, $matches)
				&& ($i === $current || strlen($matches[2]) < $mw)
			) {
				// Capture the ol item number.
				if ($type === 'ol') {
					$nums[] = intval($matches[3]);
				}

				if ($i === $current) {
				// First item.
					// Store the marker for comparison.
					$marker = $type === 'ol' ?
						$matches[4] :
						$matches[3];
				} else {
					$item++;

					$newMarker = $type === 'ol' ?
						$matches[4] :
						$matches[3];

					// Marker has changed: end of list.
					if (strcmp($marker, $newMarker) !== 0) {
						--$i;
						break;
					}
				}

				$mw = strlen($line) === strlen($matches[0]) ?
					strlen($matches[1]) + 1 :
					strlen($matches[0]);

				$line = $this->collapseTabs(
					substr($line, $mw),
					$pad
				);
				$block['items'][$item][] = $line;
			} elseif ($line === '' || ltrim($line) === '') {
			// Line is blank.
				if (!isset($lines[$i + 1])) {
				// No more lines: end of list.
					break;
				}

				$next = $this->expandTabs($lines[$i + 1], $pad);
				$line = substr($line, $mw);
				$text = ltrim(implode('', $block['items'][$item]));

				if (preg_match($pattern, $next)) {
				// Next line is a marker: loose list.
					$block['items'][$item][] = $line;
					$block['loose'] = true;
				} elseif ($text === '') {
				// 2 blank lines not followed by a marker.
					$block['items'][$item][] = $line;
					--$i;
					break;
				} elseif ($next === '' || ltrim($next) === '') {
				// Next line is also blank.
					$block['items'][$item][] = $line;
				} elseif (strspn($next, ' ' . $pad) >= $mw) {
				// Next line is indented.
					$block['items'][$item][] = $line;
				} else {
				// Next line is not list content.
					--$i;
					break;
				}
			} elseif (strspn($line, ' ' . $pad) >= $mw) {
			// Line continues the current item.
				$line = $this->collapseTabs(
					substr($line, $mw),
					$pad
				);
				$block['items'][$item][] = $line;
			} elseif (
				$this->detectLineType($lines, $i) === 'paragraph'
			) {
			// Lazy continuation line.
				$block['items'][$item][] = $lines[$i];
			} else {
			// Everything else ends the list.
				--$i;
				break;
			}
			// If next line is <hr>, end the list.
			if (
				!empty($lines[$i + 1])
				&& $this->detectLineType($lines, $i + 1) === 'hr'
			) {
				break;
			}
		}
		// Set the ol attributes.
		if ($type === 'ol') {
			$start = $nums[0];
			$end = end($nums);

			if ($start !== 1 && $this->keepListStartNumber) {
				$block['attr']['start'] = $start;
			}

			if ($start > $end && $this->keepReversedList) {
				$block['attr']['reversed'] = '';
			}
		}
		// Parse the items.
		foreach ($block['items'] as $itemId => $itemLines) {
			$blanks = 0;
			$itemBlocks = $this->parseBlocks($itemLines, $blanks);

			if (!empty($itemBlocks)) {
				if (
					$blanks > 0
					&& count($itemBlocks) > 1
				) {
				// 2+ blocks separated by blank lines: loose list.
					$block['loose'] = true;
				}
			}

			$block['items'][$itemId] = $itemBlocks;
		}

		return [$block, $i];
	}

	/**
	 * Renders a list.
	 */
	protected function renderList($block): string
	{
		$type = $block['list'];

		if (!empty($block['attr'])) {
			$output = "<$type "
				. $this->generateListAttributes($block['attr'])
				. ">\n";
		} else {
			$output = "<$type>\n";
		}

		foreach ($block['items'] as $item => $itemBlocks) {
			$li = empty($itemBlocks) ? '<li>' : "<li>\n";

			if (!$block['loose'] && !empty($itemBlocks)) {
				for ($i = count($itemBlocks) - 1; $i > -1; $i--) { 
					if ($itemBlocks[$i][0] === 'paragraph') {
						$blocks = $itemBlocks[$i]['content'];
						if ($i === 0) {
							$li = '<li>';
						}
						if (isset($itemBlocks[$i + 1])) {
							$blocks[] = ['text', "\n"];
						}
						array_splice($itemBlocks, $i, 1, $blocks);
					}
				}
			}

			$output .= $li . $this->renderAbsy($itemBlocks) . "</li>\n";
		}

		return $output . "</$type>\n";
	}

	/**
	 * Return attributes from [attrName => attrValue] list.
	 *
	 * @param array $attributes - The attribute name-value pairs.
	 * @return string
	 */
	private function generateListAttributes($attributes): string
	{
		foreach ($attributes as $name => $value) {
			$attributes[$name] = "$name=\"$value\"";
		}

		return implode(' ', $attributes);
	}

	abstract protected function collapseTabs($text, $chr = ' ');
	abstract protected function expandTabs($text, $chr = ' ');
	abstract protected function parseBlocks($lines);
	abstract protected function renderAbsy($absy);
}
