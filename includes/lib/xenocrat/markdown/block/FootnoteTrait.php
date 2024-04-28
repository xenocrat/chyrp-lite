<?php
/**
 * @copyright Copyright (c) 2014 Carsten Brandt, 2024 Daniel Pimley and other contributors
 * @license https://github.com/xenocrat/chyrp-markdown/blob/master/LICENSE
 * @link https://github.com/xenocrat/chyrp-markdown#readme
 */

namespace xenocrat\markdown\block;

/**
 * Adds footnotes.
 * 
 * Make sure to reset footnote properties on prepare():
 *
 * ```php
 * protected function prepare()
 * {
 * 	$this->footnotes = [];
 * 	$this->footnoteLinkNum = 0;
 * 	$this->footnoteLinks = [];
 * }
 * ```
 *
 * Make sure to add parsed footnotes on postprocess():
 *
 * ```php
 * protected function prepare()
 * {
 * 	return parent::postprocess(
 * 		$this->addParsedFootnotes($markup)
 * 	);
 * }
 * ```
 */
trait FootnoteTrait
{
	/**
	 * @var string[][] Unordered array of footnotes.
	 */
	protected $footnotes = [];

	/**
	 * @var int Incrementing counter of the footnote links.
	 */
	protected $footnoteLinkNum = 0;

	/**
	 * @var string[] Ordered array of footnote links.
	 */
	protected $footnoteLinks = [];

	/**
	 * @inheritDoc
	 */
	abstract protected function parseBlocks($lines);

	/**
	 * @inheritDoc
	 */
	abstract protected function renderAbsy($blocks);

	/**
	 * Add footnotes' HTML to the end of parsed HTML.
	 *
	 * @param string $html The HTML output of Markdown::parse().
	 * @return string
	 */
	public function addParsedFootnotes($html): string
	{
		// Unicode "uncertainty sign" will be used for missing references.
		$uncertaintyChr = "\u{2BD1}";

		// Sort all found footnotes by the order in which they are linked in the text.
		$footnotesSorted = [];
		$footnoteNum = 0;
		foreach ($this->footnoteLinks as $footnotePos => $footnoteLinkName) {
			foreach ($this->footnotes as $footnoteName => $footnoteHtml) {
				if ($footnoteLinkName === (string)$footnoteName) {
					// First time sorting this footnote.
					if (!isset($footnotesSorted[$footnoteName])) {
						$footnoteNum++;
						$footnotesSorted[$footnoteName] = [
							'html' => $footnoteHtml,
							'num' => $footnoteNum,
							'refs' => [1 => $footnotePos],
						];
					} else {
						// Subsequent times sorting this footnote
						// (i.e. every time it's referenced).
						$footnotesSorted[$footnoteName]['refs'][] = $footnotePos;
					}
				}
			}
		}

		$html = $this->numberFootnotes(
			$html,
			$footnotesSorted
		);

		// Add the footnote HTML to the end of the document.
		return $html . $this->getFootnotesHtml($footnotesSorted);
	}

	/**
	 * @param mixed[] $footnotesSorted Array with 'html', 'num', and 'refs' keys.
	 * @return string
	 */
	protected function getFootnotesHtml($footnotesSorted): string
	{
		if (empty($footnotesSorted)) {
			return '';
		}

		$prefix = ($this->getContextId() === '') ?
			'' :
			$this->getContextId() . '-';

		$hr = $this->html5 ? "<hr>\n" : "<hr />\n";
		$footnotesHtml = "<div class=\"footnotes\" role=\"doc-endnotes\">\n$hr<ol>\n";
		foreach ($footnotesSorted as $footnoteInfo) {
			$backLinks = [];
			foreach ($footnoteInfo['refs'] as $refIndex => $refNum) {
				$fnref = count($footnoteInfo['refs']) > 1
					? $footnoteInfo['num'] . '-' . $refIndex
					: $footnoteInfo['num'];
				$backLinks[] = '<a href="#'
					. $prefix
					. 'fnref'
					. '-'
					. $fnref
					. '" role="doc-backlink">'. "\u{21A9}\u{FE0E}" . '</a>';
			}
			$linksPara = '<p class="footnote-backrefs">'
				. join("\n", $backLinks)
				. '</p>';
			$footnotesHtml .= "<li id=\"{$prefix}fn-{$footnoteInfo['num']}\">\n"
				// Footnotes might themselves contain footnote links.
				. $this->numberFootnotes(
					$footnoteInfo['html'],
					$footnotesSorted
				)
				. $linksPara
				. "\n</li>\n";
		}
		$footnotesHtml .= "</ol>\n</div>\n";
		return $footnotesHtml;
	}

	/**
	 * @param $html string The HTML to operate on.
	 * @param mixed[] $footnotesSorted Array with 'num' and 'refs' keys.
	 * @return string
	 */
	protected function numberFootnotes($html, $footnotesSorted): string
	{
		// Unicode "uncertainty sign" will be used for missing references.
		$uncertaintyChr = "\u{2BD1}";

		// Replace all footnote placeholder links with their sorted numbers.
		return preg_replace_callback(
			"/\u{FFFC}footnote-(refnum|num)(.*?)\u{FFFC}/",
			function ($match) use ($footnotesSorted, $uncertaintyChr) {
				$footnoteName = $this->footnoteLinks[$match[2]];
				if (!isset($footnotesSorted[$footnoteName])) {
				// This is a link to a missing footnote.
					// Return the uncertainty sign.
					return $uncertaintyChr
						. ($match[1] === 'refnum' ? '-' . $match[2] : '');
				}
				if ($match[1] === 'num') {
				// Replace only the footnote number.
					return $footnotesSorted[$footnoteName]['num'];
				}
				if (count($footnotesSorted[$footnoteName]['refs']) > 1) {
				// For backlinks:
				// some have a footnote number and an additional link number.
				// If footnote is referenced more than once, add `-n` suffix.
					$linkNum = array_search(
						$match[2],
						$footnotesSorted[$footnoteName]['refs']
					);
					return $footnotesSorted[$footnoteName]['num']
						. '-'
						. $linkNum;
				} else {
				// Otherwise, just the number.
					return $footnotesSorted[$footnoteName]['num'];
				}
			},
			$html
		);
	}

	protected function parseFootnoteLinkMarkers()
	{
		return array('[^');
	}

	/**
	 * Parses a footnote link indicated by `[^`.
	 *
	 * @marker [^
	 * @param $text
	 * @return array
	 */
	protected function parseFootnoteLink($text): array
	{
		if (
			preg_match('/^\[\^(.+?)(?<!\\\\)\]/', $text, $matches)
			// unescaped brackets are not allowed
			&& !preg_match('/(?<!\\\\)[\[\]]/', $matches[1])
		) {
			$footnoteName = function_exists("mb_convert_case") ?
				mb_convert_case($matches[1], MB_CASE_FOLD, 'UTF-8') :
				strtolower($matches[1]) ;

			// We will later sort the footnotes
			// according to the order that the footnote links appear in.
			$this->footnoteLinkNum++;
			$this->footnoteLinks[$this->footnoteLinkNum] = $footnoteName;

			// To render a footnote link, we only need to know its link-number,
			// which will later be turned into its footnote-number (after sorting).
			return [
				[
					'footnoteLink',
					'num' => $this->footnoteLinkNum
				],
				strlen($matches[0])
			];
		}
		return [['text', $text[0]], 1];
	}

	/**
	 * @param string[] $block Array with 'num' key.
	 * @return string
	 */
	protected function renderFootnoteLink($block): string
	{
		$prefix = ($this->getContextId() === '') ?
			'' :
			$this->getContextId() . '-';

		$objChr = "\u{FFFC}";

		$substituteRefnum = $objChr
			. "footnote-refnum"
			. $block['num']
			. $objChr;

		$substituteNum = $objChr
			. "footnote-num"
			. $block['num']
			. $objChr;

		return '<sup id="'
			. $prefix
			. 'fnref-'
			. $substituteRefnum
			. '" class="footnote-ref">'
			. '<a href="#'
			. $prefix
			. 'fn-'
			. $substituteNum
			. '" role="doc-noteref">'
			. $substituteNum
			. '</a>'
			. '</sup>';
	}

	/**
	 * Identify a line as the beginning of a footnote block.
	 *
	 * @param $line
	 * @return false|int
	 */
	protected function identifyFootnoteList($line): bool
	{
		return preg_match('/^ {0,3}\[\^(.+?)]:/', $line);
	}

	/**
	 * Consume lines for a footnote.
	 */
	protected function consumeFootnoteList($lines, $current): array
	{
		$footnotes = [];
		$parsedFootnotes = [];
		$mw = 0;

		for ($i = $current, $count = count($lines); $i < $count; $i++) {
			$line = $lines[$i];
			$startsFootnote = preg_match(
				'/^ {0,3}\[\^(.+?)(?<!\\\\)\]:[ \t]*/',
				$line,
				$matches
			);
			if ($startsFootnote) {
				// The start of a footnote.
				$name = function_exists("mb_convert_case") ?
					mb_convert_case($matches[1], MB_CASE_FOLD, 'UTF-8') :
					strtolower($matches[1]) ;

				$mw = strlen($matches[0]);
				$str = substr($line, strlen($matches[0]));
				$footnotes[$name] = [$str];
			} elseif (
				!$startsFootnote
				&& isset($name)
				&& isset($footnotes[$name])
			) {
				if (
					ltrim($line) === ''
					&& ltrim(end($footnotes[$name])) === ''
				) {
				// Two blank lines end this list of footnotes.
					break;
				} else {
				// Current line continues the current footnote.
					$indent = strspn($line, " \t");
					$line = substr($line, ($indent < $mw ? $indent : $mw));
					$footnotes[$name][] = $line;	
				}
			} else {
				break;
			}
		}

		// Parse all collected footnotes.
		foreach ($footnotes as $footnoteName => $footnoteLines) {
			$parsedFootnotes[$footnoteName] = $this->parseBlocks($footnoteLines);
		}

		return [['footnoteList', 'content' => $parsedFootnotes], $i];
	}

	/**
	 * @param array $block
	 * @return string
	 */
	protected function renderFootnoteList($block): string
	{
		foreach ($block['content'] as $footnoteName => $footnote) {
			$this->footnotes[$footnoteName] = $this->renderAbsy($footnote);
		}
		// Render nothing, because all footnote lists will be concatenated
		// at the end of the text using the flavor's `postprocess` method.
		return '';
	}
}
