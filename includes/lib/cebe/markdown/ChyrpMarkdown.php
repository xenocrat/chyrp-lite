<?php
/**
 * @copyright Copyright (c) 2014 Carsten Brandt, 2024 Daniel Pimley
 * @license https://github.com/xenocrat/chyrp-markdown/blob/master/LICENSE
 * @link https://github.com/xenocrat/chyrp-markdown#readme
 */

namespace cebe\markdown;

/**
 * Markdown parser for [Chyrp-Flavoured Markdown](https://chyrplite.net/wiki/Chyrp-Flavoured-Markdown.html).
 */
class ChyrpMarkdown extends GithubMarkdown
{
	// include block element parsing using traits
	use block\AsideTrait;
	use block\FigureTrait;
	use block\FootnoteTrait;

	// include inline element parsing using traits
	use inline\CiteTrait;
	use inline\HighlightTrait;
	use inline\SupSubTrait;

	/**
	 * @inheritDoc
	 */
	protected $escapeCharacters = [
		// from Markdown
		'\\', // backslash
		'`', // backtick
		'*', // asterisk
		'_', // underscore
		'{', '}', // curly braces
		'[', ']', // square brackets
		'(', ')', // parentheses
		'#', // hash mark
		'+', // plus sign
		'-', // minus sign (hyphen)
		'.', // dot
		'!', // exclamation mark
		'<', '>', // angle brackets
		// added by GithubMarkdown
		':', // colon
		'|', // pipe
		// added by ChyrpMarkdown
		'='
	];

	/**
	 * @inheritDoc
	 */
	protected function prepare(): void
	{
		// reset references
		$this->references = [];

		// reset footnote properties
		$this->footnotes = [];
		$this->footnoteLinkNum = 0;
		$this->footnoteLinks = [];
	}

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
			if ($line === ''
				|| ltrim($line) === ''
				|| !ctype_alpha($line[0])
				&& (
					$this->identifyQuote($line, $lines, $i)
					|| $this->identifyFencedCode($line, $lines, $i)
					|| $this->identifyFigure($line, $lines, $i)
					|| $this->identifyAside($line, $lines, $i)
					|| $this->identifyUl($line, $lines, $i)
					|| $this->identifyOl($line, $lines, $i)
					|| $this->identifyHr($line, $lines, $i)
					|| $this->identifyHtml($line, $lines, $i)
					|| $this->identifyFootnoteList($line, $lines, $i)
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
	 * Add parsed footnotes and then post-processes markup after parsing.
	 *
	 * @param string $markup parsed markup
	 * @return string post-processed markup
	 */
	function postprocess($markup): string
	{
		return parent::postprocess($this->addParsedFootnotes($markup));
	}
}
