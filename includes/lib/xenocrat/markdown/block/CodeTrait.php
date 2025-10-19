<?php
/**
 * @copyright Copyright 2014 Carsten Brandt, 2024 Daniel Pimley
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
	 * Identify a line as the beginning of a code block.
	 */
	protected function identifyCode($line): bool
	{
		// Indentation by 4+ spaces and/or a tab is code.
		return (
			$line[0] === "\t"
			|| str_starts_with($line, '    ')
			|| str_starts_with(ltrim($line, ' '), "\t")
		);
	}

	/**
	 * Consume lines for a code block element.
	 */
	protected function consumeCode($lines, $current): array
	{
		$content = [];
		$pad = chr(29);

		// Consume until end of markers...
		for ($i = $current, $count = count($lines); $i < $count; $i++) {
			$line = $this->expandTabs($lines[$i], $pad);
			#$line = $lines[$i];
			if (strspn($line, ' ' . $pad) >= 4) {
			// A line is code if indented by 4+ spaces and/or a tab.
				$line = preg_replace(
					'/\x1D{1,4}/',
					"\t",
					substr($line, 4)
				);
				$content[] = $line;
			} elseif (
				($line === '' || ltrim($line) === '')
				&& isset($lines[$i + 1])
			) {
			// ...Or if the line is blank and the next is also blank
			// or if the next is indented by 4+ spaces and/or a tab.
				$next = $lines[$i + 1];
				if (
					$next === ''
					|| ltrim($next) === ''
					|| $next[0] === "\t"
					|| str_starts_with($next, '    ')
					|| str_starts_with(ltrim($next, ' '), "\t")
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
		// Remove leading blank lines.
		while (
			count($content) > 1
			&& ltrim(reset($content)) === ''
		) {
			array_shift($content);
		}
		// Remove trailing blank lines.
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
			' class="language-'
			. $this->escapeHtmlEntities(
				$this->unEscapeHtmlEntities(
					$this->unEscapeBackslash(
						$block['language']
					),
					ENT_QUOTES | ENT_SUBSTITUTE
				),
				ENT_COMPAT | ENT_SUBSTITUTE | ENT_DISALLOWED
			)
			. '"' : '';

		return "<pre><code$class>"
			. $this->escapeHtmlEntities(
				$block['content'],
				ENT_COMPAT | ENT_SUBSTITUTE | ENT_DISALLOWED
			)
			. ($block['content'] === '' ? '' : "\n" )
			. "</code></pre>\n";
	}

	abstract protected function escapeHtmlEntities($text, $flags = 0);
}
