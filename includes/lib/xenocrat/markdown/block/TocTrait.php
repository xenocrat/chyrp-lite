<?php
/**
 * @copyright Copyright 2024 Daniel Pimley
 * @license https://github.com/xenocrat/chyrp-markdown/blob/master/LICENSE
 * @link https://github.com/xenocrat/chyrp-markdown#readme
 */

namespace xenocrat\markdown\block;

/**
 * Adds TOC blocks.
 * 
 * Make sure to reset the TOC on prepare():
 *
 * ```php
 * protected function prepare()
 * {
 * 	$this->toc = [];
 * }
 * ```
 *
 * Make sure to add the TOC on postprocess():
 *
 * ```php
 * protected function prepare()
 * {
 * 	return parent::postprocess(
 * 		$this->addToc($markup)
 * 	);
 * }
 * ```
 */
trait TocTrait
{
	/**
	 * @var mixed[] - Headings detected and rendered in the text.
	 */
	protected $toc = [];

	/**
	 * Identify a line as a TOC.
	 */
	protected function identifyToc($line, $lines, $current): bool
	{
		return preg_match('/^ {0,3}(\[(\[_)?TOC(_\])?\])([ \t]|$)/', $line);
	}

	/**
	 * Consume lines for a TOC.
	 */
	protected function consumeToc($lines, $current): array
	{
		return [['toc'], $current];
	}

	/**
	 * Renders a TOC.
	 */
	protected function renderToc($block): string
	{
		$objChr = "\u{FFFC}";

		// Render a placeholder to be populated
		// using the flavor's `postprocess` method.
		return "{$objChr}[[_TOC_]]{$objChr}\n";
	}

	/**
	 * Renders a headline and adds it to the TOC.
	 * This method overloads HeadlineTrait::renderHeadline().
	 */
	protected function renderHeadline($block): string
	{
		$tag = 'h' . $block['level'];
		$id = '';
		$content = $this->renderAbsy($block['content']);

		if (
			class_exists('\\IntlChar')
			&& function_exists('mb_str_split')
			&& function_exists('mb_convert_case')
		) {
			$str = $this->unEscapeHtmlEntities(
				strip_tags($content),
				ENT_QUOTES | ENT_SUBSTITUTE
			);

			$exploded = mb_str_split($str, 1, 'UTF-8');

			foreach ($exploded as $chr) {
				$type = \IntlChar::charType($chr);
				if (
					$chr === ' ' || $chr === "\t" || $chr === '-' || $chr === '_'
					|| $type === \IntlChar::CHAR_CATEGORY_UPPERCASE_LETTER
					|| $type === \IntlChar::CHAR_CATEGORY_LOWERCASE_LETTER
					|| $type === \IntlChar::CHAR_CATEGORY_TITLECASE_LETTER
					|| $type === \IntlChar::CHAR_CATEGORY_MODIFIER_LETTER
					|| $type === \IntlChar::CHAR_CATEGORY_OTHER_LETTER
					|| $type === \IntlChar::CHAR_CATEGORY_DECIMAL_DIGIT_NUMBER
					|| $type === \IntlChar::CHAR_CATEGORY_LETTER_NUMBER
					|| $type === \IntlChar::CHAR_CATEGORY_CONNECTOR_PUNCTUATION
					|| $type === \IntlChar::CHAR_CATEGORY_NON_SPACING_MARK
					|| $type === \IntlChar::CHAR_CATEGORY_ENCLOSING_MARK
					|| $type === \IntlChar::CHAR_CATEGORY_COMBINING_SPACING_MARK
				) {
					$id .= ($chr === ' ' || $chr === "\t") ?
						'-' :
						mb_convert_case($chr, MB_CASE_LOWER, 'UTF-8');
				}
			}

			if ($id !== '') {
				$prefix = ($this->getContextId() === '') ?
					'' :
					$this->getContextId() . '-';

				while (isset($this->headlineAnchorLinks[$id])) {
					$id .= '-' . $this->headlineAnchorLinks[$id]++;
				}

				$this->headlineAnchorLinks[$id] = 1;

				$this->toc[] = [
					'id' => $id,
					'level' => $block['level'],
					'content' => $content,
				];

				$id = ' id="'
					. $prefix
					. $this->escapeHtmlEntities(
						$id,
						ENT_COMPAT | ENT_SUBSTITUTE
					)
					. '"';
			}
		}

		return "<{$tag}{$id}>{$content}</{$tag}>\n";
	}

	/**
	 * Add Toc's HTML to the parsed HTML.
	 *
	 * @param string $html - The HTML output of Markdown::parse().
	 * @return string
	 */
	public function addToc($html): string
	{
		$objChr = "\u{FFFC}";
		$toc = '';
		$depth = 2;
		$items = 0;

		$prefix = ($this->getContextId() === '') ?
			'' :
			$this->getContextId() . '-';

		if (!empty($this->toc)) {
			$toc .= "<ul>\n";

			foreach ($this->toc as $h) {
				// Ignore h1; this is the document title.
				if ($h['level'] < 2) {
					continue;
				}
				// Go deeper in hierarchy if necessary.
				while ($depth < 6 && $depth < $h['level']) {
					$depth++;
					$toc .= "<ul>\n";
				}
				// Go higher in hierarchy if necessary.
				while ($depth > 2 && $depth > $h['level']) {
					$depth--;
					$toc .= "</ul>\n";
				}

				$id = $prefix
					. $this->escapeHtmlEntities(
						$h['id'],
						ENT_COMPAT | ENT_SUBSTITUTE
					);

				$toc .= "<li><a href=\"#{$id}\">{$h['content']}</a></li>\n";
				$items++;
			}

			$toc .= '</ul>';
		}

		// Replace TOC placeholder.
		return str_replace(
			"{$objChr}[[_TOC_]]{$objChr}",
			($items) ? $toc : '',
			$html
		);
	}

	abstract protected function renderAbsy($absy);
	abstract protected function escapeHtmlEntities($text, $flags = 0);
	abstract protected function unEscapeHtmlEntities($text, $flags = 0);
}
