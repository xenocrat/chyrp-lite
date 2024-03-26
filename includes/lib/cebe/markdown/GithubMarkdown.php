<?php
/**
 * @copyright Copyright (c) 2014 Carsten Brandt, 2024 Daniel Pimley
 * @license https://github.com/xenocrat/chyrp-markdown/blob/master/LICENSE
 * @link https://github.com/xenocrat/chyrp-markdown#readme
 */

namespace cebe\markdown;

/**
 * Markdown parser for [GitHub-Flavored Markdown](https://github.github.com/gfm/).
 *
 * @author Carsten Brandt <mail@cebe.cc>
 */
class GithubMarkdown extends Markdown
{
	// include block element parsing using traits
	use block\TableTrait;

	// include inline element parsing using traits
	use inline\StrikeoutTrait;
	use inline\AutoLinkTrait;

	/**
	 * @var boolean Whether to interpret newlines as `<br />` tags.
	 * This feature is useful for comments where newlines are often
	 * meant to be hard line breaks.
	 */
	public $enableNewlines = false;

	/**
	 * Consume lines for a paragraph.
	 *
	 * Allow other block types to break paragraphs.
	 */
	protected function consumeParagraph($lines, $current): array
	{
		$content = [];

		// consume until blank line or end condition
		for ($i = $current, $count = count($lines); $i < $count; $i++) {
			$line = $lines[$i];
			if (
				$line === ''
				|| ltrim($line) === ''
				|| !ctype_alpha($line[0])
				&& (
					$this->identifyQuote($line, $lines, $i)
					|| $this->identifyFencedCode($line, $lines, $i)
					|| $this->identifyUl($line, $lines, $i)
					|| $this->identifyOl($line, $lines, $i)
					|| $this->identifyHr($line, $lines, $i)
					|| $this->identifyHtml($line, $lines, $i)
				)
				|| $this->identifyHeadline($line, $lines, $i)
			) {
				break;
			} else {
				$content[] = ltrim($line);
			}
		}
		$block = [
			'paragraph',
			'content' => $this->parseInline(trim(implode("\n", $content))),
		];

		return [$block, --$i];
	}

	/**
	 * @inheritDoc
	 *
	 * Parses a newline indicated by two or more spaces on the end of a markdown line.
	 */
	protected function renderText($text): string
	{
		if ($this->enableNewlines) {
			$br = $this->html5 ? "<br>\n" : "<br />\n";
			$text[1] = preg_replace("/ *\n/", $br, $text[1]);
		}
		return parent::renderText($text);
	}
}
