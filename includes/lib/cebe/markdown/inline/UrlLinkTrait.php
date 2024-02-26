<?php
/**
 * @copyright Copyright (c) 2014 Carsten Brandt
 * @license https://github.com/xenocrat/chyrp-markdown/blob/master/LICENSE
 * @link https://github.com/xenocrat/chyrp-markdown#readme
 */

namespace cebe\markdown\inline;

/**
 * Adds auto linking for URLs
 */
trait UrlLinkTrait
{
	protected function parseUrlMarkers(): array
	{
		return array('www.', 'http', 'ftp');
	}

	/**
	 * Parses urls and adds auto linking feature.
	 * @marker www.
	 * @marker http
	 * @marker ftp
	 */
	protected function parseUrl($text): array
	{
		$regex = <<<REGEXP
			/(?(R) # in case of recursion match parentheses
				 \(((?>[^\s()]+)|(?R))*\)
			|      # else match a link with title
				^(https?:\/\/|ftp:\/\/|www.)(([^\s<>()]+)|(?R))+(?<![\.,:;\'"!\?\s])
			)/x
REGEXP;
		if (!in_array('parseLink', $this->context) && preg_match($regex, $text, $matches)) {
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
		if (!preg_match('/^(http|ftp)/', $href)) {
			$href = 'http://' . $href;
		}
		$ent = $this->html5 ? ENT_HTML5 : ENT_HTML401;
		$href = htmlspecialchars($href, ENT_COMPAT | $ent, 'UTF-8');
		$decoded = urldecode($text);
		$secured = preg_match('//u', $decoded) ? $decoded : $text;
		$text = htmlspecialchars($secured, ENT_NOQUOTES | ENT_SUBSTITUTE, 'UTF-8');
		return "<a href=\"$href\">$text</a>";
	}
}
