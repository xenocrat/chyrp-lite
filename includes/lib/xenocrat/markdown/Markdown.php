<?php
/**
 * @copyright Copyright 2014 Carsten Brandt, 2024-2026 Daniel Pimley
 * @license https://github.com/xenocrat/chyrp-markdown/blob/master/LICENSE
 * @link https://github.com/xenocrat/chyrp-markdown#readme
 */

namespace xenocrat\markdown;

/**
 * Markdown parser for CommonMark.
 *
 * @see https://spec.commonmark.org/
 * @author Carsten Brandt <mail@cebe.cc>
 */
class Markdown extends Parser
{
	// Include block element parsing using traits.
	use block\CodeTrait;
	use block\FencedCodeTrait;
	use block\HeadlineTrait;
	use block\HtmlTrait;
	use block\ListTrait;
	use block\QuoteTrait;
	use block\RuleTrait;

	// Include inline element parsing using traits.
	use inline\CodeTrait;
	use inline\EmphStrongTrait;
	use inline\LinkTrait;

	/**
	 * @var array - These are "escapeable" characters.
	 *
	 * When using one of these prefixed with a backslash, the character is
	 * not interpreted as markdown and will be outputted without backslash.
	 */
	protected $escapeCharacters = [
		'\\', // backslash
		'/', // forward slash
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
		',', // comma
		'!', // exclamation mark
		'<', '>', // angle brackets
		'"', // double quote
		'\'', // single quote
		'$', // dollar sign
		'%', // percent sign
		'&', // ampersand
		':', // colon
		';', // semicolon
		'=', // equals sign
		'?', // question mark
		'@', // at symbol
		'~', // tilde
		'^', // caret
		'|', // pipe
	];

	/**
	 * @inheritDoc
	 */
	protected $blockPriorities = [
		'Hr',
		'Ul',
		'FencedCode',
		'Code',
		'Html',
		'Ol',
		'Quote',
		'Reference',
		'SetextHeadline',
		'AtxHeadline',
	];

	/**
	 * @inheritDoc
	 */
	protected function prepare(): void
	{
		// Reset references.
		$this->references = [];

		// Reset anchor links.
		$this->headlineAnchorLinks = [];
	}

	/**
	 * @inheritDoc
	 *
	 * Allow other block types to break paragraphs.
	 */
	protected function consumeParagraph($lines, $current): array
	{
		$content = [];

		// Consume until blank line or end condition...
		for ($i = $current, $count = count($lines); $i < $count; $i++) {
			$line = $lines[$i];
			if (
				$line === ''
				|| ($trimmed = ltrim($line)) === ''
				|| (
					(ctype_punct($trimmed[0]) || ctype_digit($trimmed[0]))
					&& (
						$this->identifyQuote($line, $lines, $i)
						|| $this->identifyFencedCode($line, $lines, $i)
						|| $this->identifyUl($line, $lines, $i)
						|| $this->identifyOl($line, $lines, $i)
						|| $this->identifyHr($line, $lines, $i)
						|| $this->identifyHtml($line, $lines, $i)
					)
				)
				|| $this->identifyAtxHeadline($line, $lines, $i)
			) {
				break;
			} else {
				if ($this->identifySetextHeadline($line, $lines, $i)) {
					return $this->consumeSetextHeadline($lines, $current);
				} else {
					$content[] = ltrim($line);
				}
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
	 * Parses a newline indicated by a backslash on the end of a markdown line.
	 *
	 * @marker \
	 */
	protected function parseEscape($text): array
	{
		$br = $this->html5 ? "<br>\n" : "<br />\n";
		if (isset($text[1]) && $text[1] === "\n") {
		// Backslash followed by newline.
			return [['text', $br], 2];
		}
		// Otherwise parse the sequence normally.
		return parent::parseEscape($text);
	}

	/**
	 * @inheritDoc
	 *
	 * Parses a newline indicated by two or more spaces on the end of a markdown line.
	 */
	protected function renderText($text): string
	{
		$br = $this->html5 ? "<br>\n" : "<br />\n";
		$text = $text[1];
		// Two or more spaces.
		$text = preg_replace("/ {2,}\n/", $br, $text);
		// Trim single spaces.
		$text = str_replace(" \n", "\n", $text);
		return $text;
	}
}
