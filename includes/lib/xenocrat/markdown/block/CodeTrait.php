<?php
/**
 * @copyright Copyright (c) 2014 Carsten Brandt, 2024 Daniel Pimley
 * @license https://github.com/xenocrat/chyrp-markdown/blob/master/LICENSE
 * @link https://github.com/xenocrat/chyrp-markdown#readme
 */

namespace xenocrat\markdown\block;

/**
 * Adds indented code blocks.
 */
trait CodeTrait
{
	/**
	 * identify a line as the beginning of a code block.
	 */
	protected function identifyCode($line): bool
	{
		// indentation >= 4 or one tab is code
		return $line[0] === "\t" || strncmp($line, '    ', 4) === 0;
	}

	/**
	 * Consume lines for a code block element.
	 */
	protected function consumeCode($lines, $current): array
	{
		$content = [];

		// consume until end of markers
		for ($i = $current, $count = count($lines); $i < $count; $i++) {
			$line = $lines[$i];
			if (isset($line[0]) && $line[0] === "\t") {
			// a line belongs to this code block if indented by a tab
				$line = substr($line, 1);
				$content[] = $line;
			} elseif (str_starts_with($line, '    ')) {
			// a line belongs to this code block if indented by 4 spaces
				$line = substr($line, 4);
				$content[] = $line;
			} elseif (
				($line === '' || ltrim($line) === '')
				&& isset($lines[$i + 1])
			) {
			// ...or if blank and the next is also blank
			// or the next is indented by 4 spaces or a tab
				$next = $lines[$i + 1];
				if (
					$next === ''
					|| ltrim($next) === ''
					|| $next[0] === "\t"
					|| str_starts_with($next, '    ')
				) {
					$line = '';
					$content[] = $line;
				} else {
					break;
				}
			} else {
				break;
			}
		}
		// remove leading blank lines
		while (
			count($content) > 1
			&& ltrim(reset($content)) === ''
		) {
			array_shift($content);
		}
		// remove trailing blank lines
		while (
			count($content) > 1
			&& ltrim(end($content)) === ''
		) {
			array_pop($content);
		}
		$block = [
			'code',
			'content' => implode("\n", $content),
		];

		return [$block, --$i];
	}

	/**
	 * Renders a code block.
	 */
	protected function renderCode($block): string
	{
		$class = isset($block['language']) ?
			' class="language-' . $block['language'] . '"' : '';
		return "<pre><code$class>"
			. $this->escapeHtmlEntities(
				$block['content'],
				ENT_COMPAT | ENT_SUBSTITUTE
			)
			. ($block['content'] === '' ? '' : "\n" )
			. "</code></pre>\n";
	}
}
