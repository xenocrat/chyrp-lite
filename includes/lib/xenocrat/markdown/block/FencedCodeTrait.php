<?php
/**
 * @copyright Copyright (c) 2014 Carsten Brandt, 2024 Daniel Pimley
 * @license https://github.com/xenocrat/chyrp-markdown/blob/master/LICENSE
 * @link https://github.com/xenocrat/chyrp-markdown#readme
 */

namespace xenocrat\markdown\block;

/**
 * Adds fenced code blocks.
 *
 * Automatically includes indented code block support.
 */
trait FencedCodeTrait
{
	use CodeTrait;

	/**
	 * Identify a line as the beginning of a fenced code block.
	 */
	protected function identifyFencedCode($line): bool
	{
		return (
			preg_match('/^ {0,3}~{3,}/', $line)
			|| preg_match('/^ {0,3}`{3,}[^`]*$/', $line)
		);
	}

	/**
	 * Consume lines for a fenced code block.
	 */
	protected function consumeFencedCode($lines, $current): array
	{
		$indent = strspn($lines[$current], ' ');
		$line = substr($lines[$current], $indent);
		$mw = strspn($line, $line[0]);
		$fence = substr($line, 0, $mw);
		$language = trim(substr($line, $mw));
		$content = [];

		// Consume until end fence...
		for ($i = $current + 1, $count = count($lines); $i < $count; $i++) {
			$line = $lines[$i];
			$leadingSpaces = strspn($line, ' ');
			if (
				$leadingSpaces > 3
				|| strspn(ltrim($line), $fence[0]) < $mw
				|| !str_ends_with(rtrim($line), $fence[0])
			) {
				if ($indent > 0 && $leadingSpaces > 0) {
					if ($leadingSpaces < $indent) {
						$line = ltrim($line);
					} else {
						$line = substr($line, $indent);
					}
				}
				$content[] = $line;
			} else {
				break;
			}
		}
		$block = [
			'code',
			'content' => implode("\n", $content),
		];
		if ($language !== '') {
			if (preg_match('/^[^ ]+/', $language, $match)) {
				$block['language'] = $this->unEscapeBackslash($match[0]);
			}
		}

		return [$block, $i];
	}
}
