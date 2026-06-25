<?php
/**
 * @copyright Copyright 2014 Carsten Brandt, 2024-2026 Daniel Pimley
 * @license https://github.com/xenocrat/chyrp-markdown/blob/master/LICENSE
 * @link https://github.com/xenocrat/chyrp-markdown#readme
 */

namespace xenocrat\markdown\inline;

/**
 * Adds auto linking for unbracketed URLs.
 */
trait AutoLinkTrait
{
	protected function parseAutoUrlMarkers(
	): array {
		return array('www.', 'http');
	}

	/**
	 * Parses urls and adds auto linking feature.
	 *
	 * @marker www.
	 * @marker http
	 */
	protected function parseAutoUrl(
		$markdown
	): array {
		if (
			// Do not allow links within links.
			!in_array('parseLink', $this->context)
			// Link?
			&& preg_match(
				'/^(www\.|https?:\/\/)
					((?:[a-zA-Z0-9\-_]\.)*[a-zA-Z0-9\-]+\.[a-zA-Z0-9\-]+[^\s<]*)
					(?<![~_\*\.,:\!\?])/x',
				$markdown,
				$matches
			)
		) {
			if (str_ends_with($matches[0], ';')) {
				$matches[0] = preg_replace(
					'/(&[a-z0-9]+;)+$/i',
					'',
					$matches[0]
				);
			} else {
				while (
					str_ends_with($matches[0], ')')
					&& substr_count($matches[0], ')')
						> substr_count($matches[0], '(')
				) {
					$matches[0] = substr($matches[0], 0, -1);
				}
			}

			return [
				['autoUrl', $matches[0]],
				strlen($matches[0])
			];
		}

		return [['text', substr($markdown, 0, 4)], 4];
	}

	protected function renderAutoUrl(
		$block
	): string {
		$href = $block[1];
		$text = $href;

		if (!str_starts_with($href, 'http')) {
			$href = 'http://' . $href;
		}

		$href = $this->escapeHtmlEntities($href, ENT_COMPAT);
		$decoded = rawurldecode($text);
		$secured = preg_match('//u', $decoded) ? $decoded : $text;

		$text = $this->escapeHtmlEntities(
			$secured,
			ENT_NOQUOTES | ENT_SUBSTITUTE | ENT_DISALLOWED
		);

		return "<a href=\"$href\">$text</a>";
	}

	protected function parseAutoEmailMarkers(
	): array {
		return array('mailto:');
	}

	/**
	 * Parses email addresses and adds auto linking feature.
	 *
	 * @marker mailto:
	 */
	protected function parseAutoEmail(
		$markdown
	): array {
		if (
			// Do not allow links within links.
			!in_array('parseLink', $this->context)
			// Email?
			&& preg_match(
				'/^mailto:
					([\.\w\d\-_+]+@
					(?:[a-zA-Z0-9\-_]+\.)+[a-zA-Z0-9\-_]*[a-zA-Z0-9]+)
					(?![\w\d\-_])/ux',
				$markdown,
				$matches
			)
		) {
			return [
				['autoEmail', $matches[0]],
				strlen($matches[0])
			];
		}

		return [['text', substr($markdown, 0, 7)], 7];
	}

	protected function renderAutoEmail(
		$block
	): string {
		$email = $this->escapeHtmlEntities(
			$block[1],
			ENT_NOQUOTES | ENT_SUBSTITUTE | ENT_DISALLOWED
		);

		return "<a href=\"$email\">$email</a>";
	}

	protected function parseAutoXMPPMarkers(
	): array {
		return array('xmpp:');
	}

	/**
	 * Parses XMPP entity addresses and adds auto linking feature.
	 *
	 * @marker xmpp:
	 */
	protected function parseAutoXMPP(
		$markdown
	): array {
		if (
			// Do not allow links within links.
			!in_array('parseLink', $this->context)
			// XMPP entity?
			&& preg_match(
				'/^xmpp:
					([\.\w\d\-_+]+@
					(?:[a-zA-Z0-9\-_]+\.)+[a-zA-Z0-9\-_]*[a-zA-Z0-9]+)
					(?:\/[@\.\w\d]+)?
					(?![\w\d\-_])/ux',
				$markdown,
				$matches
			)
		) {
			return [
				['autoXMPP', $matches[0]],
				strlen($matches[0])
			];
		}

		return [['text', substr($markdown, 0, 5)], 5];
	}

	protected function renderAutoXMPP(
		$block
	): string {
		$xmpp = $this->escapeHtmlEntities(
			$block[1],
			ENT_NOQUOTES | ENT_SUBSTITUTE | ENT_DISALLOWED
		);

		return "<a href=\"$xmpp\">$xmpp</a>";
	}

	abstract protected function escapeHtmlEntities(
		$text,
		$flags = 0
	);

	abstract protected function renderText(
		$block
	);
}
