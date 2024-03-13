<?php
/**
 * @copyright Copyright (c) 2014 Carsten Brandt, 2024 Daniel Pimley
 * @license https://github.com/xenocrat/chyrp-markdown/blob/master/LICENSE
 * @link https://github.com/xenocrat/chyrp-markdown#readme
 */

namespace cebe\markdown;

/**
 * Markdown parser for [CommonMark](https://spec.commonmark.org/).
 *
 * @author Carsten Brandt <mail@cebe.cc>
 */
class Markdown extends Parser
{
	// include block element parsing using traits
	use block\CodeTrait;
	use block\FencedCodeTrait;
	use block\HeadlineTrait;
	use block\HtmlTrait;
	use block\ListTrait;
	use block\QuoteTrait;
	use block\RuleTrait;

	// include inline element parsing using traits
	use inline\CodeTrait;
	use inline\EmphStrongTrait;
	use inline\LinkTrait;

	/**
	 * @var array these are "escapeable" characters. When using one of these prefixed with a
	 * backslash, the character will be outputted without the backslash and is not interpreted
	 * as markdown.
	 */
	protected $escapeCharacters = [
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
	];

	/**
	 * @inheritDoc
	 */
	protected function prepare(): void
	{
		// reset references
		$this->references = [];
	}

	/**
	 * Consume lines for a paragraph
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
				|| !ctype_alpha($line[0]) && (
					$this->identifyQuote($line, $lines, $i) ||
					$this->identifyFencedCode($line, $lines, $i) ||
					$this->identifyUl($line, $lines, $i) ||
					$this->identifyOl($line, $lines, $i) ||
					$this->identifyHr($line, $lines, $i) ||
					$this->identifyHtml($line, $lines, $i)
				)
				|| $this->identifyHeadline($line, $lines, $i)) {
				break;
			} else {
				$content[] = $line;
			}
		}
		$block = [
			'paragraph',
			'content' => $this->parseInline(implode("\n", $content)),
		];
		return [$block, --$i];
	}

	/**
	 * @inheritDoc
	 *
	 * Parses a newline indicated by a backslash on the end of a markdown line.
	 *
	 * @marker \
	 */
	protected function parseEscape($text): array
	{
		$br = $this->html5 ? "<br>\n" : "<br />\n";
		if (isset($text[1]) && $text[1] === "\n") {
		// backslash followed by newline
			return [['text', $br], 2];
		} elseif (!isset($text[1])) {
		// backslash at end of the text
			return [['text', $br], 1];
		}

		// Otherwise parse the sequence normally
		return parent::parseEscape($text);
	}

	/**
	 * @inheritDoc
	 *
	 * Parses a newline indicated by two spaces on the end of a markdown line.
	 */
	protected function renderText($text): string
	{
		$br = $this->html5 ? "<br>\n" : "<br />\n";
		return str_replace("  \n", $br, $text[1]);
	}
}
