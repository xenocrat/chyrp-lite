<?php
/**
 * @copyright Copyright (c) 2014 Carsten Brandt, 2024 Daniel Pimley
 * @license https://github.com/xenocrat/chyrp-markdown/blob/master/LICENSE
 * @link https://github.com/xenocrat/chyrp-markdown#readme
 */

namespace xenocrat\markdown;

/**
 * Markdown parser for Chyrp-Flavoured Markdown.
 *
 * @see https://chyrplite.net/wiki/Chyrp-Flavoured-Markdown.html
 */
class ChyrpMarkdown extends GithubMarkdown
{
	// Include block element parsing using traits.
	use block\AsideTrait;
	use block\FigureTrait;
	use block\FootnoteTrait;

	// Include inline element parsing using traits.
	use inline\CiteTrait;
	use inline\HighlightTrait;
	use inline\SupSubTrait;

	/**
	 * @inheritDoc
	 */
	protected $blockPriorities = [
		'Hr',
		'Aside',
		'Ul',
		'Code',
		'FencedCode',
		'Figure',
		'FootnoteList',
		'Html',
		'Ol',
		'Quote',
		'Reference',
		'Table',
		'Headline',
	];

	/**
	 * @inheritDoc
	 */
	protected function prepare(): void
	{
		parent::prepare();

		// Reset footnote properties.
		$this->footnotes = [];
		$this->footnoteLinkNum = 0;
		$this->footnoteLinks = [];
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
			if ($line === ''
				|| ($trimmed = ltrim($line)) === ''
				|| (
					(ctype_punct($trimmed[0]) || ctype_digit($trimmed[0]))
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
	 * Add parsed footnotes and then post-processes markup after parsing.
	 */
	function postprocess($markup): string
	{
		return parent::postprocess(
			$this->addParsedFootnotes($markup)
		);
	}
}
