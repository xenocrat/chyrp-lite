<?php
/**
 * @copyright Copyright (c) 2023 Carsten Brandt and other contributors
 * @license https://github.com/cebe/markdown/blob/master/LICENSE
 * @link https://github.com/cebe/markdown#readme
 */

namespace cebe\markdown;

/**
 * Markdown parser to extend Github flavored markdown for Chyrp Lite.
 */
class ChyrpMarkdown extends GithubMarkdown
{
	// include block element parsing using traits
	use block\FootnoteTrait;

    // include inline element parsing using traits
    use inline\HighlightTrait;

    /**
     * @inheritDoc
     */
    function parse($text)
    {
        return $this->addParsedFootnotes(parent::parse($text));
    }
}
