<?php
/**
 * @copyright Copyright 2023-2026 Daniel Pimley
 * @license https://github.com/xenocrat/chyrp-markdown/blob/master/LICENSE
 * @link https://github.com/xenocrat/chyrp-markdown#readme
 */

namespace xenocrat\markdown\inline;

/**
 * Adds highlight inline elements.
 */
trait HighlightTrait
{
	protected function parseHighlightMarkers(
	): array {
		return array('==');
	}

	/**
	 * Parses the highlight feature.
	 *
	 * @marker ==
	 */
	protected function parseHighlight(
		$markdown
	): array {
		if (
			preg_match(
				'/^
					# Opening marker:
					(={2,})
					# First char cannot be a delimiter.
					(?!=)
					# Capture...
					# any backslash escaped char;
					# or any char except backslash and delimiter;
					# or delimeter run longer than opening marker;
					# or delimiter run shorter than opening marker:
					((?>(?:\\\\.|[^\\\\=]|\1=+|(?!\1)=+)+))
					# Closing marker:
					\1
					# Next char must not be a delimiter.
					(?!=)/sx',
				$markdown,
				$matches
			)
		) {
			if (
				// Inline HTML, link, image, or code takes precedence.
				!$this->detectInlineOverrun(
					$markdown,
					strlen($matches[0]),
					['Lt', 'Link', 'Image', 'InlineCode']
				)
			) {
				return [
					[
						'highlight',
						$this->parseInline($matches[2])
					],
					strlen($matches[0])
				];
			}
		}

		return [
			[
				'text',
				$markdown[0] . substr(
					$markdown,
					1,
					($spn = strspn($markdown, '=', 1))
				)
			],
			++$spn
		];
	}

	protected function renderHighlight(
		$block
	): string {
		return '<mark>'
			. $this->renderAbsy($block[1])
			. '</mark>';
	}

	abstract protected function detectInlineOverrun(
		$text,
		$length,
		$elements
	);

	abstract protected function parseInline(
		$text
	);

	abstract protected function renderAbsy(
		$blocks
	);

	abstract protected function renderText(
		$block
	);
}
