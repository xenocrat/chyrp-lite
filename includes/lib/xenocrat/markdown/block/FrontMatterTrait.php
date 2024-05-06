<?php
/**
 * @copyright Copyright 2024 Daniel Pimley
 * @license https://github.com/xenocrat/chyrp-markdown/blob/master/LICENSE
 * @link https://github.com/xenocrat/chyrp-markdown#readme
 */

namespace xenocrat\markdown\block;

/**
 * Adds front matter blocks.
 */
trait FrontMatterTrait
{
	/**
	 * @var bool - Render front matter as a code block.
	 */
	public $renderFrontMatter = true;

	/**
	 * Identify a line as the beginning of a front matter block.
	 */
	protected function identifyFrontMatter($line): bool
	{
		return (
			preg_match('/^ {0,3};{3,}[^;]*$/', $line)
			|| preg_match('/^ {0,3}\-{3,}[^\-]*$/', $line)
			|| preg_match('/^ {0,3}\+{3,}[^\+]*$/', $line)
		);
	}

	/**
	 * Consume lines for a front matter block.
	 */
	protected function consumeFrontMatter($lines, $current): array
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

		return [($this->renderFrontMatter) ? $block : false, $i];
	}

	abstract protected function renderCode($block);
	abstract protected function unEscapeBackslash($text);
}
