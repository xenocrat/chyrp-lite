<?php
/**
 * @copyright Copyright (c) 2023 Carsten Brandt and other contributors
 * @license https://github.com/cebe/markdown/blob/master/LICENSE
 * @link https://github.com/cebe/markdown#readme
 */

namespace cebe\markdown;

/**
 * Markdown parser to extend Github flavored markdown for Chyrp Lite.
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
	 * Consume lines for a paragraph
	 *
	 * Allow headlines, lists, code, figures and asides to break paragraphs
	 */
	protected function consumeParagraph($lines, $current)
	{
		// consume until newline
		$content = [];
		for ($i = $current, $count = count($lines); $i < $count; $i++) {
			$line = $lines[$i];
			if ($line === ''
				|| ltrim($line) === ''
				|| !ctype_alpha($line[0]) && (
					$this->identifyQuote($line, $lines, $i) ||
					$this->identifyFencedCode($line, $lines, $i) ||
					$this->identifyFigure($line, $lines, $i) ||
					$this->identifyAside($line, $lines, $i) ||
					$this->identifyUl($line, $lines, $i) ||
					$this->identifyOl($line, $lines, $i) ||
					$this->identifyHr($line, $lines, $i)
				)
				|| $this->identifyHeadline($line, $lines, $i))
			{
				break;
			} elseif ($this->identifyCode($line, $lines, $i)) {
				// possible beginning of a code block
				// but check for continued inline HTML
				// e.g. <img src="file.jpg"
				//           alt="some alt aligned with src attribute" title="some text" />
				if (preg_match('~<\w+([^>]+)$~s', implode("\n", $content))) {
					$content[] = $line;
				} else {
					break;
				}
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
	 */
	function parse($text)
	{
		return $this->addParsedFootnotes(parent::parse($text));
	}
}
