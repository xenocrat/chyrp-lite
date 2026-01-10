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
			return [
				[
					'sup',
					$this->parseInline($matches[1])
				],
				strlen($matches[0])
			];
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
			return [
				[
					'sub',
					$this->parseInline($matches[1])
				],
				strlen($matches[0])
			];
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
