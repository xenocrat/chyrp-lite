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
		$marker = '';
		$mw = 0;
		$looseList = false;
		// consume until end condition
		for ($i = $current, $count = count($lines); $i < $count; $i++) {
			$line = $lines[$i];
			$pattern = ($type === 'ol') ?
				'/^( {0,3})(\d+)([\.\)])([ \t]+|$)/' :
				'/^( {0,3})([\-\+\*])([ \t]+|$)/';
			// if not the first item, marker indentation must be less than
			// width of preceeding marker - otherwise it is a continuation
			// of the current item containing a marker for a sub-list item
			if (preg_match($pattern, $line, $matches)
				&& ($i === $current || strlen($matches[1]) < $mw)) {
				if ($i === $current) {
				// first item
					// store the marker for comparison
					$marker = $type === 'ol' ? $matches[3] : $matches[2];
					// set the ol start attribute
					if ($type === 'ol' && $this->keepListStartNumber) {
						$block['attr']['start'] = $matches[2];
					}
				} else {
					$item++;
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
				$block['items'][$item][] = $line;
			} elseif ($line === '' || ltrim($line) === '') {
				if (!isset($lines[$i + 1])) {
				// no more lines: end of list
					break;
				} elseif ($lines[$i + 1] === '' || ltrim($lines[$i + 1]) === '') {
				// next line is also blank
					$block['items'][$item][] = $line;
				} elseif (ctype_space(substr($lines[$i + 1], 0, $mw))) {
				// next line is indented enough to continue this item
					$block['items'][$item][] = $line;
				} elseif (preg_match($pattern, $lines[$i + 1])) {
				// next line is the next item in this list: loose list
					$block['items'][$item][] = $line;
					$looseList = true;
				} else {
				// everything else ends the list
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
			}
		}

		foreach ($block['items'] as $itemId => $itemLines) {
			$content = [];
			if (!$looseList) {
			// tight list:
			// parse inline unless a non-paragraph block marker is detected
				$paragraph = [];
				while (isset($itemLines[0]) && isset($itemLines[0][0])
					&& $this->detectLineType($itemLines, 0) === 'paragraph') {
					$paragraph[] = array_shift($itemLines);
				}
				$content = $this->parseInline(implode("\n", $paragraph));
			}
			if (!empty($itemLines)) {
				// render any blocks that remain in the item content
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
