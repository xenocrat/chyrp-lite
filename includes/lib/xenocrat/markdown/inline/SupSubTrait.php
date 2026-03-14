<?php
/**
 * @copyright Copyright 2023-2026 Daniel Pimley
 * @license https://github.com/xenocrat/chyrp-markdown/blob/master/LICENSE
 * @link https://github.com/xenocrat/chyrp-markdown#readme
 */

namespace xenocrat\markdown\inline;

/**
 * Adds superscript and subscript inline elements.
 */
trait SupSubTrait
{
	protected function parseSupMarkers(): array
	{
		return array('++');
	}

	/**
	 * Parses the superscript feature.
	 *
	 * @marker ++
	 */
	protected function parseSup($markdown): array
	{
		if (
			preg_match(
				'/^\+\+(?!\+)(.*?([^\+\\\\]|(?<=\\\\)\+))\+\+(?!\+)/s',
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
			$matches[1] = str_replace(
				'\\\\'.chr(31),
				'\\\\',
				$matches[1]
			);
			$content = $matches[1];
			if (
				// Inline HTML takes precedence.
				(
					!method_exists($this, 'parseLt')
					|| ($pos = strpos($content, $this->parseLtMarkers()[0]))
						=== false
					|| ($arr = $this->parseLt(substr($markdown, (2 + $pos))))[0][0]
						=== 'text'
					|| $arr[1] <= (strlen($content) - $pos)
				)
				// Inline link takes precedence.
				&& (
					!method_exists($this, 'parseLink')
					|| ($pos = strpos($content, $this->parseLinkMarkers()[0]))
						=== false
					|| ($arr = $this->parseLink(substr($markdown, (2 + $pos))))[0][0]
						=== 'text'
					|| $arr[1] <= (strlen($content) - $pos)
				)
				// Inline image takes precedence.
				&& (
					!method_exists($this, 'parseImage')
					|| ($pos = strpos($content, $this->parseImageMarkers()[0]))
						=== false
					|| ($arr = $this->parseImage(substr($markdown, (2 + $pos))))[0][0]
						=== 'text'
					|| $arr[1] <= (strlen($content) - $pos)
				)
			) {
				return [
					[
						'sup',
						$this->parseInline($content)
					],
					strlen($matches[0])
				];
			}
		}
		return [['text', $markdown[0] . $markdown[1]], 2];
	}

	protected function renderSup($block): string
	{
		return '<sup>'
			. $this->renderAbsy($block[1])
			. '</sup>';
	}

	protected function parseSubMarkers(): array
	{
		return array('--');
	}

	/**
	 * Parses the subscript feature.
	 *
	 * @marker --
	 */
	protected function parseSub($markdown): array
	{
		if (
			preg_match(
				'/^--(?!-)(.*?([^-\\\\]|(?<=\\\\)-))--(?!-)/s',
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
			$matches[1] = str_replace(
				'\\\\'.chr(31),
				'\\\\',
				$matches[1]
			);
			$content = $matches[1];
			if (
				// Inline HTML takes precedence.
				(
					!method_exists($this, 'parseLt')
					|| ($pos = strpos($content, $this->parseLtMarkers()[0]))
						=== false
					|| ($arr = $this->parseLt(substr($markdown, (2 + $pos))))[0][0]
						=== 'text'
					|| $arr[1] <= (strlen($content) - $pos)
				)
				// Inline link takes precedence.
				&& (
					!method_exists($this, 'parseLink')
					|| ($pos = strpos($content, $this->parseLinkMarkers()[0]))
						=== false
					|| ($arr = $this->parseLink(substr($markdown, (2 + $pos))))[0][0]
						=== 'text'
					|| $arr[1] <= (strlen($content) - $pos)
				)
				// Inline image takes precedence.
				&& (
					!method_exists($this, 'parseImage')
					|| ($pos = strpos($content, $this->parseImageMarkers()[0]))
						=== false
					|| ($arr = $this->parseImage(substr($markdown, (2 + $pos))))[0][0]
						=== 'text'
					|| $arr[1] <= (strlen($content) - $pos)
				)
			) {
				return [
					[
						'sub',
						$this->parseInline($content)
					],
					strlen($matches[0])
				];
			}
		}
		return [['text', $markdown[0] . $markdown[1]], 2];
	}

	protected function renderSub($block): string
	{
		return '<sub>'
			. $this->renderAbsy($block[1])
			. '</sub>';
	}

	abstract protected function renderText($block);
	abstract protected function parseInline($text);
	abstract protected function renderAbsy($blocks);
}
