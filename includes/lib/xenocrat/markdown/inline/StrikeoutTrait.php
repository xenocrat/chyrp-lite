<?php
/**
 * @copyright Copyright 2014 Carsten Brandt, 2024-2026 Daniel Pimley
 * @license https://github.com/xenocrat/chyrp-markdown/blob/master/LICENSE
 * @link https://github.com/xenocrat/chyrp-markdown#readme
 */

namespace xenocrat\markdown\inline;

/**
 * Adds strikeout inline elements.
 */
trait StrikeoutTrait
{
	protected function parseStrikeMarkers(): array
	{
		return array('~');
	}

	/**
	 * Parses the strikethrough feature.
	 *
	 * @marker ~
	 */
	protected function parseStrike($markdown): array
	{
		if (
			preg_match(
				'/^(~{1,2})(?!~)(.*?([^~\\\\]|(?<=\\\\)~))\1(?!~)/s',
				str_replace(
					'\\\\',
					'\\\\'.chr(31),
					$markdown
				),
				$matches
			)
		) {
			$matches[0] = str_replace(
				'\\\\'.chr(31),
				'\\\\',
				$matches[0]
			);
			$matches[2] = str_replace(
				'\\\\'.chr(31),
				'\\\\',
				$matches[2]
			);
			$content = $matches[2];
			$mw = strlen($matches[1]);
			if (
				// Inline HTML takes precedence.
				(
					!method_exists($this, 'parseLt')
					|| ($pos = strpos($content, $this->parseLtMarkers()[0]))
						=== false
					|| ($arr = $this->parseLt(substr($markdown, ($mw + $pos))))[0][0]
						=== 'text'
					|| $arr[1] <= (strlen($content) - $pos)
				)
				// Inline link takes precedence.
				&& (
					!method_exists($this, 'parseLink')
					|| ($pos = strpos($content, $this->parseLinkMarkers()[0]))
						=== false
					|| ($arr = $this->parseLink(substr($markdown, ($mw + $pos))))[0][0]
						=== 'text'
					|| $arr[1] <= (strlen($content) - $pos)
				)
				// Inline image takes precedence.
				&& (
					!method_exists($this, 'parseImage')
					|| ($pos = strpos($content, $this->parseImageMarkers()[0]))
						=== false
					|| ($arr = $this->parseImage(substr($markdown, ($mw + $pos))))[0][0]
						=== 'text'
					|| $arr[1] <= (strlen($content) - $pos)
				)
			) {
				return [
					[
						'strike',
						$this->parseInline($content)
					],
					strlen($matches[0])
				];
			}
		}
		return [['text', $markdown[0]], 1];
	}

	protected function renderStrike($block): string
	{
		return '<del>'
			. $this->renderAbsy($block[1])
			. '</del>';
	}

	abstract protected function renderText($block);
	abstract protected function parseInline($text);
	abstract protected function renderAbsy($blocks);
}
