<?php
/**
 * @copyright Copyright (c) 2014 Carsten Brandt
 * @license https://github.com/cebe/markdown/blob/master/LICENSE
 * @link https://github.com/cebe/markdown#readme
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

	/**
	 * LinkTrait conflicts with HtmlTrait. If both traits are used together,
	 * you must define the HtmlTrait::parseInlineHtml method as private so
	 * it is not used directly:
	 *
	 * ```php
	 * use block\HtmlTrait {
	 *     parseInlineHtml as private parseInlineHtml;
	 * }
	 * ```
	 *
	 * If the HtmlTrait::parseInlineHtml method exists it will be called from
	 * within LinkTrait::parseLt if needed.
	 */
	use block\HtmlTrait {
		parseInlineHtml as private;
	}

	use block\ListTrait;
	use block\QuoteTrait;
	use block\RuleTrait;

	// include inline element parsing using traits
	use inline\CodeTrait;
	use inline\EmphStrongTrait;
	use inline\LinkTrait;

	/**
	 * @var boolean whether to format markup according to HTML5 spec.
	 * Defaults to `false` which means that markup is formatted as HTML4.
	 */
	public $html5 = false;

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
		'<', '>',
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
		// consume until newline
		$content = [];
		for ($i = $current, $count = count($lines); $i < $count; $i++) {
			$line = $lines[$i];

			// a list may break a paragraph when it is inside of a list
			if (isset($this->context[1]) && $this->context[1] === 'list' && !ctype_alpha($line[0]) && (
				$this->identifyUl($line, $lines, $i) || $this->identifyOl($line, $lines, $i))) {
				break;
			}

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
	 * Parses a newline indicated by two spaces on the end of a markdown line.
	 */
	protected function renderText($text): string
	{
		return str_replace("  \n", $this->html5 ? "<br>\n" : "<br />\n", $text[1]);
	}
}
