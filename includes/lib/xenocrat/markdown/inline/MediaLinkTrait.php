<?php
/**
 * @copyright Copyright 2024 Daniel Pimley
 * @license https://github.com/xenocrat/chyrp-markdown/blob/master/LICENSE
 * @link https://github.com/xenocrat/chyrp-markdown#readme
 */

namespace xenocrat\markdown\inline;

/**
 * Adds images, embedded audio and video.
 * This method overloads LinkTrait::renderImage().
 */
trait MediaLinkTrait
{
	/**
	 * @var bool Render video and audio with a deferred loading attribute.
	 */
	public $renderLazyMedia = false;

	protected function renderImage($block): string
	{
		if (isset($block['refkey'])) {
			if (($ref = $this->lookupReference($block['refkey'])) !== false) {
				$block = array_merge($block, $ref);
			} else {
				if (str_starts_with($block['orig'], '![')) {
					return '!['
					. $this->renderAbsy(
						$this->parseInline(substr($block['orig'], 2))
					);
				}
				return $block['orig'];
			}
		}
		if (
			preg_match('/\.(mpe?g|mp4|m4v|mov|webm|ogv)$/i', $block['url'])
		) {
			return '<video controls="" src="'
				. $this->escapeHtmlEntities($block['url'], ENT_COMPAT) . '"'
				. (
					empty($block['width']) ?
						'' :
						' width="' . $block['width'] . '"'
				)
				. (
					empty($block['height']) ?
						'' :
						' height="' . $block['height'] . '"'
				)
				. (
					$this->renderLazyMedia ?
						' preload="none"' :
						' preload="metadata"'
				)
				. (
					empty($block['title']) ?
						'' :
						' title="'
						. $this->escapeHtmlEntities(
							$this->unEscapeHtmlEntities(
								$this->unEscapeBackslash(
									$block['title']
								),
								ENT_QUOTES | ENT_SUBSTITUTE
							),
							ENT_COMPAT | ENT_SUBSTITUTE | ENT_DISALLOWED
						)
						. '"'
				)
				. '>'
				. $this->renderAbsy($this->parseInline($block['text']))
				. '</video>';
		} elseif (
			preg_match('/\.(mp3|m4a|oga|ogg|spx|wav|aiff?)$/i', $block['url'])
		) {
			return '<audio controls="" src="'
				. $this->escapeHtmlEntities($block['url'], ENT_COMPAT) . '"'
				. (
					empty($block['width']) ?
						'' :
						' width="' . $block['width'] . '"'
				)
				. (
					empty($block['height']) ?
						'' :
						' height="' . $block['height'] . '"'
				)
				. (
					$this->renderLazyMedia ?
						' preload="none"' :
						' preload="metadata"'
				)
				. (
					empty($block['title']) ?
						'' :
						' title="'
						. $this->escapeHtmlEntities(
							$this->unEscapeHtmlEntities(
								$this->unEscapeBackslash(
									$block['title']
								),
								ENT_QUOTES | ENT_SUBSTITUTE
							),
							ENT_COMPAT | ENT_SUBSTITUTE | ENT_DISALLOWED
						)
						. '"'
				)
				. '>'
				. $this->renderAbsy($this->parseInline($block['text']))
				. '</audio>';
		} else {
			return '<img src="'
				. $this->escapeHtmlEntities(
					$this->unEscapeHtmlEntities(
						$this->unEscapeBackslash(
							$block['url']
						),
						ENT_QUOTES | ENT_SUBSTITUTE
					),
					ENT_COMPAT | ENT_SUBSTITUTE | ENT_DISALLOWED
				)
				. '"'
				. ' alt="'
				. $this->escapeHtmlEntities(
					strip_tags(
						$this->renderAbsy(
							$this->parseInline($block['text'])
						)
					),
					ENT_COMPAT | ENT_SUBSTITUTE | ENT_DISALLOWED
				)
				. '"'
				. (
					empty($block['width']) ?
						'' :
						' width="' . $block['width'] . '"'
				)
				. (
					empty($block['height']) ?
						'' :
						' height="' . $block['height'] . '"'
				)
				. (
					$this->renderLazyImages ?
						' loading="lazy"' :
						''
				)
				. (
					empty($block['title']) ?
						'' :
						' title="'
						. $this->escapeHtmlEntities(
							$this->unEscapeHtmlEntities(
								$this->unEscapeBackslash(
									$block['title']
								),
								ENT_QUOTES | ENT_SUBSTITUTE
							),
							ENT_COMPAT | ENT_SUBSTITUTE | ENT_DISALLOWED
						)
						. '"'
				)
				. ($this->html5 ? '>' : ' />');
		}
	}

	abstract protected function parseImage($markdown);
	abstract protected function parseInline($text);
	abstract protected function renderAbsy($blocks);
	abstract protected function escapeHtmlEntities($text, $flags = 0);
}
