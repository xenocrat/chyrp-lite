<?php
/**
 * @copyright Copyright 2014 Carsten Brandt, 2024 Daniel Pimley
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
		return preg_match('/^ {0,3}\d{1,9}[\.\)]([ \t]|$)/', $line);
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

		// Consume until end condition...
		for ($i = $current, $count = count($lines); $i < $count; $i++) {
			$line = $this->expandTabs($lines[$i]);
			$pattern = ($type === 'ol') ?
				'/^( {0,3})(\d{1,9})([\.\)])([ \t]+|$)/' :
				'/^( {0,3})([\-\+\*])([ \t]+|$)/' ;
			// If not the first item, marker indentation must be less than
			// width of preceeding marker - otherwise it is a continuation
			// of the current item containing a marker for a sub-list item.
			if (
				preg_match($pattern, $line, $matches)
				&& ($i === $current || strlen($matches[1]) < $mw)
			) {
				// Capture the ol item number.
				if ($type === 'ol') {
					$nums[] = intval($matches[2]);
				}
				if ($i === $current) {
				// First item.
					// Store the marker for comparison.
					$marker = $type === 'ol' ?
						$matches[3] :
						$matches[2] ;
				} else {
					$item++;

					$newMarker = $type === 'ol' ?
						$matches[3] :
						$matches[2] ;

					// Marker has changed: end of list.
					if (strcmp($marker, $newMarker) !== 0) {
						--$i;
						break;
					}
				}
				$mw = strlen($matches[0]);
				$line = substr($line, $mw);
				$block['items'][$item][] = $line;
			} elseif ($line === '' || ltrim($line) === '') {
				// No more lines: end of list.
				if (!isset($lines[$i + 1])) {
					break;
				}
				$next = $this->expandTabs($lines[$i + 1]);
				$line = substr($line, $mw);
				if ($next === '' || ltrim($next) === '') {
				// Next line is also blank.
					$block['items'][$item][] = $line;
				} elseif (strspn($next, " \t") >= $mw) {
				// Next line is indented enough to continue this item.
					$block['items'][$item][] = $line;
				} elseif (preg_match($pattern, $next)) {
				// Next line is the next item in this list: loose list.
					$block['items'][$item][] = $line;
					$block['loose'] = true;
				} else {
				// next line is not list content.
					break;
				}
			} elseif (
				strlen($line) > $mw
				&& strspn($line, " \t") >= $mw
			) {
				// Line is indented enough to continue this item.
				$line = substr($line, $mw);
				$block['items'][$item][] = $line;
			} else {
				// Everything else ends the list.
				--$i;
				break;
			}

			// If next line is <hr>, end the list.
			if (
				!empty($lines[$i + 1])
				&& method_exists($this, 'identifyHr')
				&& $this->identifyHr($lines[$i + 1])
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
		// Tight list? Check it...
		if (!$block['loose']) {
			foreach ($block['items'] as $itemLines) {
				// Empty list item.
				if (ltrim($itemLines[0]) === '' && !isset($itemLines[1])) {
					continue;
				}
				// Everything else.
				for ($x = 0; $x < count($itemLines); $x++) {
					if (
						ltrim($itemLines[$x]) === ''
						|| $this->detectLineType($itemLines, $x) !== 'paragraph'
					) {
						// Blank line or non-paragraph block marker detected:
						// make the list loose because block parsing is required.
						$block['loose'] = true;
						break 2;
					}
				}
			}
		}
		foreach ($block['items'] as $itemId => $itemLines) {
			$block['items'][$itemId] = $block['loose'] ?
				$this->parseBlocks($itemLines) :
				$this->parseInline(implode("\n", $itemLines)) ;
		}
		return [$block, $i];
	}

	/**
	 * Renders a list.
	 */
	protected function renderList($block): string
	{
		$type = $block['list'];
		$li = $block['loose'] ? "<li>\n" : '<li>';

		if (!empty($block['attr'])) {
			$output = "<$type "
				. $this->generateHtmlAttributes($block['attr'])
				. ">\n";
		} else {
			$output = "<$type>\n";
		}

		foreach ($block['items'] as $item => $itemLines) {
			$output .= $li . $this->renderAbsy($itemLines). "</li>\n";
		}
		return $output . "</$type>\n";
	}

	/**
	 * Return HTML attributes string from [attrName => attrValue] list.
	 *
	 * @param array $attributes - The attribute name-value pairs.
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
