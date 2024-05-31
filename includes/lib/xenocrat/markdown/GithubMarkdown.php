<?php
/**
 * @copyright Copyright 2014 Carsten Brandt, 2024 Daniel Pimley
 * @license https://github.com/xenocrat/chyrp-markdown/blob/master/LICENSE
 * @link https://github.com/xenocrat/chyrp-markdown#readme
 */

namespace xenocrat\markdown;

/**
 * Markdown parser for GitHub-Flavored Markdown.
 *
 * @see https://github.github.com/gfm/
 * @author Carsten Brandt
 * @author Daniel Pimley
 */
class GithubMarkdown extends Markdown
{
	// Include block element parsing using traits.
	use block\TableTrait;

	// Include inline element parsing using traits.
	use inline\AutoLinkTrait;
	use inline\CheckboxTrait;
	use inline\StrikeoutTrait;

	/**
	 * @inheritDoc
	 */
	protected $blockPriorities = [
		'Hr',
		'Ul',
		'Code',
		'FencedCode',
		'Html',
		'Ol',
		'Quote',
		'Reference',
		'Table',
		'Headline',
	];

	/**
	 * @var boolean Whether to interpret newlines as `<br />` tags.
	 *
	 * This feature is useful for comments where newlines are often
	 * meant to be hard line breaks.
	 */
	public $enableNewlines = false;

	/**
	 * @inheritDoc
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
	 * Parses all newlines as hard line breaks if `enableNewlines` is set.
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
