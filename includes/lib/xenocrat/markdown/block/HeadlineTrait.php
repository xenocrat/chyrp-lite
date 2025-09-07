<?php
/**
 * @copyright Copyright 2014 Carsten Brandt, 2024 Daniel Pimley
 * @license https://github.com/xenocrat/chyrp-markdown/blob/master/LICENSE
 * @link https://github.com/xenocrat/chyrp-markdown#readme
 */

namespace xenocrat\markdown\block;

/**
 * Adds headline blocks.
 * 
 * Make sure to reset anchor link counter on prepare():
 *
 * ```php
 * protected function prepare()
 * {
 * 	$this->headlineAnchorLinks = [];
 * }
 * ```
 */
trait HeadlineTrait
{
	/**
	 * @var bool - Generate an `id` attribute for headline anchors.
	 */
	public $headlineAnchors = false;

	/**
	 * @var int[] - Incrementing counter of rendered anchor links.
	 */
	protected $headlineAnchorLinks = [];

	/**
	 * Identify a line as ATX headline.
	 */
	protected function identifyAtxHeadline($line, $lines, $current): bool
	{
		return (preg_match('/^( {0,3}(\#{1,6}))([ \t]|$)/', $line));
	}

	/**
	 * Identify a line as Setext headline.
	 */
	protected function identifySetextHeadline($line, $lines, $current): bool
	{
		return (
			!empty($lines[$current + 1])
			&& preg_match('/^ {0,3}(\-+|=+)\s*$/', $lines[$current + 1])
		);
	}

	/**
	 * Consume lines for ATX headline.
	 */
	protected function consumeAtxHeadline($lines, $current): array
	{
		preg_match(
			'/^( {0,3}(\#{1,6}))([ \t]|$)/',
			$lines[$current],
			$matches
		);
		$line = substr($lines[$current], strlen($matches[1]));
		$line = preg_replace('/[ \t]+(\#+[ \t]*)?$/', '', $line);
		$block = [
			'headline',
			'content' => $this->parseInline(trim($line)),
			'level' => strlen($matches[2]),
		];
		return [$block, $current];
	}

	/**
	 * Consume lines for Setext headline.
	 */
	protected function consumeSetextHeadline($lines, $current): array
	{
		$content = [];
		for ($i = $current, $count = count($lines); $i < $count; $i++) {
			if (preg_match('/^ {0,3}(\-+|=+)\s*$/', $lines[$i])) {
				break;
			}
			$content[] = trim($lines[$i]);
		}
		$block = [
			'headline',
			'content' => $this->parseInline(trim(implode("\n", $content))),
			'level' => substr_count($lines[$i], '=') ? 1 : 2,
		];
		return [$block, $i];
	}

	/**
	 * Renders a headline.
	 */
	protected function renderHeadline($block): string
	{
		$tag = 'h' . $block['level'];
		$id = '';
		$content = $this->renderAbsy($block['content']);

		if (
			$this->headlineAnchors
			&& class_exists('\\IntlChar')
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
					$chr === ' ' || $chr === '-' || $chr === '_'
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
					$id .= ($chr === ' ') ?
						'-' :
						mb_convert_case($chr, MB_CASE_LOWER, 'UTF-8');
				}
			}

			if ($id !== '') {
				$prefix = $this->getContextId();
				if ($prefix !== '') {
					$prefix .= '-';
				}

				while (isset($this->headlineAnchorLinks[$id])) {
					$id .= '-' . $this->headlineAnchorLinks[$id]++;
				}

				$this->headlineAnchorLinks[$id] = 1;

				$id = ' id="'
					. $prefix
					. $this->escapeHtmlEntities(
						$id,
						ENT_COMPAT | ENT_SUBSTITUTE | ENT_DISALLOWED
					)
					. '"';
			}
		}

		return "<{$tag}{$id}>{$content}</{$tag}>\n";
	}

	abstract protected function parseInline($text);
	abstract protected function renderAbsy($absy);
	abstract protected function escapeHtmlEntities($text, $flags = 0);
	abstract protected function unEscapeHtmlEntities($text, $flags = 0);
}
