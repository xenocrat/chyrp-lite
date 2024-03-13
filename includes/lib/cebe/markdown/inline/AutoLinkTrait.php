<?php
/**
 * @copyright Copyright (c) 2014 Carsten Brandt
 * @license https://github.com/xenocrat/chyrp-markdown/blob/master/LICENSE
 * @link https://github.com/xenocrat/chyrp-markdown#readme
 */

namespace cebe\markdown\inline;

/**
 * Adds auto linking for unbracketed URLs.
 */
trait AutoLinkTrait
{
	protected function parseAutoUrlMarkers(): array
	{
		return array('www.', 'http');
	}

	/**
	 * Parses urls and adds auto linking feature.
	 * @marker www.
	 * @marker http
	 */
	protected function parseAutoUrl($text): array
	{
		$regex = <<<REGEXP
			/(?(R) # in case of recursion match parentheses
				 \(((?>[^\s()]+)|(?R))*\)
			|      # else match a link with title
				^(www\.|https?:\/\/)(([^\s<>()]+)|(?R))+(?<![\.,:;\'"!\?\s])
			)/x
REGEXP;
		if (!in_array('parseLink', $this->context) && preg_match($regex, $text, $matches)) {
		// do not allow links in links
			return [
				['autoUrl', $matches[0]],
				strlen($matches[0])
			];
		}
		return [['text', substr($text, 0, 4)], 4];
	}

	protected function renderAutoUrl($block): string
	{
		$href = $block[1];
		$text = $href;
		if (strncmp($href, 'http', 4) !== 0) {
			$href = 'http://' . $href;
		}
		$href = $this->escapeHtmlEntities($href, ENT_COMPAT);
		$decoded = urldecode($text);
		$secured = preg_match('//u', $decoded) ? $decoded : $text;
		$text = $this->escapeHtmlEntities($secured, ENT_NOQUOTES | ENT_SUBSTITUTE);
		return "<a href=\"$href\">$text</a>";
	}
}
