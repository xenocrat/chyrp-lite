<?php
/**
 * @copyright Copyright 2023-2026 Daniel Pimley
 * @license https://github.com/xenocrat/chyrp-markdown/blob/master/LICENSE
 * @link https://github.com/xenocrat/chyrp-markdown#readme
 */

namespace xenocrat\markdown\inline;

/**
 * Adds cite inline elements.
 */
trait CiteTrait
{
	protected function parseCiteMarkers(
	): array {
		return array('*_');
	}

	/**
	 * Parses the cite feature.
	 *
	 * @marker *_
	 * @see https://www.unicode.org/reports/tr44/#General_Category_Values
	 */
	protected function parseCite(
		$markdown
	): array {
		if (
			preg_match(
				'/^
					# Opening marker:
					\*(_{1,})
					# First char cannot be a delimiter.
					# First char cannot be whitespace.
					# First char cannot be Unicode category Zs, Pe, Pf.
					(?![_\s\p{Zs}\p{Pe}\p{Pf}])
					# Capture...
					# any backslash escaped char;
					# or any char except backslash and delimiter;
					# or delimeter run longer than opening marker;
					# or delimiter run shorter than opening marker:
					((?>(?:\\\\.|[^\\\\_]|\1_+|(?!\1\*)_+)+))
					# Last char cannot be whitespace.
					# Last char cannot be Unicode category Zs, Ps, Pi.
					(?<![\s\p{Zs}\p{Ps}\p{Pi}])
					# Closing marker:
					\1\*/usx',
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
						'cite',
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
					($spn = strspn($markdown, '_', 1))
				)
			],
			++$spn
		];
	}

	protected function renderCite(
		$block
	): string {
		return '<cite>'
			. $this->renderAbsy($block[1])
			. '</cite>';
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
