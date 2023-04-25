<?php
/**
 * @copyright Copyright (c) 2023 Carsten Brandt and other contributors
 */

namespace cebe\markdown;

/**
 * Markdown parser to extend Github flavored markdown for Chyrp Lite.
 */
class ChyrpMarkdown extends GithubMarkdown
{
	// include block element parsing using traits
	use block\FootnoteTrait;

    /**
     * @inheritDoc
     */
    function parse($text)
    {
        return $this->addParsedFootnotes(parent::parse($text));
    }
}
