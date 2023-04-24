<?php

namespace cebe\markdown;

/**
 * Markdown parser for Chyrp Lite flavored markdown.
 */
class ChyrpMarkdown extends GithubMarkdown
{
	// include block element parsing using traits
	use block\FootnoteTrait;

    /**
     * @inheritdoc
     */
    function parse($text)
    {
        return $this->addParsedFootnotes(parent::parse($text));
    }
}
