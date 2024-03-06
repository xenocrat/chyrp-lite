<?php
/**
 * @copyright Copyright (c) 2014 Carsten Brandt
 * @license https://github.com/xenocrat/chyrp-markdown/blob/master/LICENSE
 * @link https://github.com/xenocrat/chyrp-markdown#readme
 */

namespace cebe\markdown\block;

/**
 * Adds the 4 space indented code blocks
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
	 * Consume lines for a code block element
	 */
	protected function consumeCode($lines, $current): array
	{
		$content = [];
		// consume until end of markers
		for ($i = $current, $count = count($lines); $i < $count; $i++) {
			$line = $lines[$i];
			// a line belongs to this code block if it is indented by 4 spaces or a tab
			if (isset($line[0]) && ($line[0] === "\t" || strncmp($line, '    ', 4) === 0)) {
				$line = $line[0] === "\t" ? substr($line, 1) : substr($line, 4);
				$content[] = $line;
			// ...or if blank and the next is also blank or indented by 4 spaces or a tab
			} elseif (($line === '' || ltrim($line) === '') && isset($lines[$i + 1])) {
				$next = $lines[$i + 1];
				if ($next === '' ||
					ltrim($next) === '' ||
					$next[0] === "\t" ||
					strncmp($next, '    ', 4) === 0) {
					if (isset($line[0])
						&& ($line[0] === "\t" || strncmp($line, '    ', 4) === 0)) {
						$line = $line[0] === "\t" ? substr($line, 1) : substr($line, 4);
					} else {
						$line = '';
					}
					$content[] = $line;
				} else {
					break;
				}
			} else {
				break;
			}
		}

		$block = [
			'code',
			'content' => implode("\n", $content),
		];
		return [$block, --$i];
	}

	/**
	 * Renders a code block
	 */
	protected function renderCode($block): string
	{
		$class = isset($block['language']) ? ' class="language-' . $block['language'] . '"' : '';
		return "<pre><code$class>"
			. htmlspecialchars($block['content'] . "\n", ENT_NOQUOTES | ENT_SUBSTITUTE, 'UTF-8')
			. "</code></pre>\n";
	}
}
