<?php
/**
 * @copyright Copyright (c) 2014 Carsten Brandt
 * @license https://github.com/cebe/markdown/blob/master/LICENSE
 * @link https://github.com/cebe/markdown#readme
 */

namespace cebe\markdown;
use ReflectionMethod;

/**
 * A generic parser for markdown-like languages.
 *
 * @author Carsten Brandt <mail@cebe.cc>
 */
abstract class Parser
{
	const VERSION_MAJOR = 2;
	const VERSION_MINOR = 0;

	/**
	 * @var integer the maximum nesting level for language elements.
	 */
	public $maximumNestingLevel = 32;

	/**
	 * @var boolean whether to convert all tabs into 4 spaces.
	 */
	public $convertTabsToSpaces = false;

	/**
	 * @var string optional context identifier for this instance.
	 */
	public $contextID = '';

	/**
	 * @var array the current context the parser is in.
	 */
	protected $context = [];

	/**
	 * @var array these are "escapeable" characters.
	 * When using one of these prefixed with a backslash, the character is
	 * not interpreted as markdown and will be outputted without backslash.
	 */
	protected $escapeCharacters = [
		'\\', // backslash
	];

	private $_depth = 0;

	/**
	 * Parses the given text considering the full language.
	 *
	 * This includes parsing block elements as well as inline elements.
	 *
	 * @param string $text the text to parse
	 * @return string parsed markup
	 */
	public function parse($text): string
	{
		$this->prepare();

		if (ltrim($text) === '') {
			return '';
		}

		$text = $this->preprocess($text);

		$this->prepareMarkers($text);

		$absy = $this->parseBlocks(explode("\n", $text));
		$markup = $this->renderAbsy($absy);
		$markup = $this->postprocess($markup);

		$this->cleanup();
		return $markup;
	}

	/**
	 * Parses a paragraph without block elements (block elements are ignored).
	 *
	 * @param string $text the text to parse
	 * @return string parsed markup
	 */
	public function parseParagraph($text): string
	{
		$this->prepare();

		if (ltrim($text) === '') {
			return '';
		}

		$text = $this->preprocess($text);

		$this->prepareMarkers($text);

		$absy = $this->parseInline($text);
		$markup = $this->renderAbsy($absy);
		$markup = $this->postprocess($markup);

		$this->cleanup();
		return $markup;
	}

	/**
	 * Pre-processes text before parsing.
	 *
	 * @param string $text the text to parse
	 * @return string pre-processed text
	 */
	protected function preprocess($text): string
	{
		if ($this->convertTabsToSpaces) {
			$text = str_replace("\t", "    ", $text);
		}

		$text = str_replace(["\r\n", "\n\r", "\r"], "\n", $text);
		return $text;
	}

	/**
	 * Post-processes markup after parsing.
	 *
	 * @param string $markup parsed markup
	 * @return string post-processed markup
	 */
	protected function postprocess($markup): string
	{
		$safe = function_exists('mb_chr') ?
			mb_chr(0xFFFD, 'UTF-8') : '&#xFFFD;';

		$markup = str_replace("\0", $safe, $markup);
		return $markup;
	}

	/**
	 * This method will be called before `parse()` and `parseParagraph()`.
	 * You can override it to do some initialization work.
	 */
	protected function prepare(): void
	{
	}

	/**
	 * This method will be called after `parse()` and `parseParagraph()`.
	 * You can override it to do cleanup.
	 */
	protected function cleanup(): void
	{
	}

	// block parsing

	private $_blockTypes;

	/**
	 * @return array a list of block element types available.
	 *
	 * You can bust the alphabetical sort/call strategy with a `Priority` method
	 * matching the identify method name, returning a different string to compare.
	 * E.g. identifyUl() and identifyUlPriority().
	 */
	protected function blockTypes(): array
	{
		if ($this->_blockTypes === null) {
			// detect block types via "identify" functions
			$reflection = new \ReflectionClass($this);
			$this->_blockTypes = array_filter(array_map(function($method) {
				$methodName = $method->getName();
				return (strncmp($methodName, 'identify', 8) === 0
					&& substr_compare($methodName, 'Priority', -8) !== 0) ?
					substr($methodName, 8) : false;
			}, $reflection->getMethods(ReflectionMethod::IS_PROTECTED)));

			usort($this->_blockTypes, function($a, $b) {
				$a_method = 'identify' . $a . 'Priority';
				$a_priority = method_exists($this, $a_method) ?
					$this->{$a_method}() : $a;

				$b_method = 'identify' . $b . 'Priority';
				$b_priority = method_exists($this, $b_method) ?
					$this->{$b_method}() : $b;

				return strcasecmp($a_priority, $b_priority);
			});
		}
		return $this->_blockTypes;
	}

	/**
	 * Given a set of lines and an index of a current line it uses the registed
	 * block types to detect the type of this line.
	 * @param array $lines
	 * @param integer $current
	 * @return string name of the block type in lower case
	 */
	protected function detectLineType($lines, $current): string
	{
		$line = $lines[$current];
		$blockTypes = $this->blockTypes();
		foreach($blockTypes as $blockType) {
			if ($this->{'identify' . $blockType}($line, $lines, $current)) {
				return $blockType;
			}
		}
		// consider the line a normal paragraph if no other block type matches
		return 'paragraph';
	}

	/**
	 * Parse block elements by calling `detectLineType()` to identify them
	 * and call consume function afterwards.
	 */
	protected function parseBlocks($lines): array
	{
		if ($this->_depth >= $this->maximumNestingLevel) {
			// maximum depth is reached, do not parse input
			return [['text', implode("\n", $lines)]];
		}
		$this->_depth++;

		$blocks = [];

		// convert lines to blocks
		for ($i = 0, $count = count($lines); $i < $count; $i++) {
			$line = $lines[$i];
			if ($line !== '' && rtrim($line) !== '') {
			// skip empty lines
				// identify beginning of a block and parse the content
				list($block, $i) = $this->parseBlock($lines, $i);
				if ($block !== false) {
					$blocks[] = $block;
				}
			}
		}

		$this->_depth--;

		return $blocks;
	}

	/**
	 * Parses the block at current line by identifying the block type and parsing the content
	 * @param $lines
	 * @param $current
	 * @return array Array of two elements:
	 * 			(array) the parsed block;
	 * 			(int) the the next line index to be parsed.
	 */
	protected function parseBlock($lines, $current): array
	{
		// identify block type for this line
		$blockType = $this->detectLineType($lines, $current);

		// call consume method for the detected block type to consume further lines
		return $this->{'consume' . $blockType}($lines, $current);
	}

	protected function renderAbsy($blocks): string
	{
		$output = '';
		foreach ($blocks as $block) {
			array_unshift($this->context, $block[0]);
			$output .= $this->{'render' . $block[0]}($block);
			array_shift($this->context);
		}
		return $output;
	}

	/**
	 * Consume lines for a paragraph
	 *
	 * @param $lines
	 * @param $current
	 * @return array
	 */
	protected function consumeParagraph($lines, $current): array
	{
		// consume until newline
		$content = [];
		for ($i = $current, $count = count($lines); $i < $count; $i++) {
			if (ltrim($lines[$i]) !== '') {
				$content[] = $lines[$i];
			} else {
				break;
			}
		}
		$block = [
			'paragraph',
			'content' => $this->parseInline(implode("\n", $content)),
		];
		return [$block, --$i];
	}

	/**
	 * Render a paragraph block
	 *
	 * @param $block
	 * @return string
	 */
	protected function renderParagraph($block): string
	{
		return '<p>' . $this->renderAbsy($block['content']) . "</p>\n";
	}

	// inline parsing

	/**
	 * @var array the set of inline markers to use in different contexts.
	 */
	private $_inlineMarkers = [];

	/**
	 * Returns a map of inline markers to the corresponding parser methods.
	 *
	 * This array defines handler methods for inline markdown markers.
	 * When a marker is found in the text, the handler method is called with the text
	 * starting at the position of the marker.
	 *
	 * Note that markers starting with whitespace may slow down the parser,
	 * so it may be better to use [[renderText]] to deal with them instead.
	 *
	 * You may override this method to define a set of markers and parsing methods.
	 * The default implementation looks for protected methods starting with `parse`
	 * with a matching `Markers` method. E.g. parseEscape() and parseEscapeMarkers().
	 *
	 * @return array a map of markers to parser methods
	 */
	protected function inlineMarkers(): array
	{
		$markers = [];
		// detect "parse" functions
		$reflection = new \ReflectionClass($this);
		foreach($reflection->getMethods(ReflectionMethod::IS_PROTECTED) as $method) {
			$methodName = $method->getName();
			if (strncmp($methodName, 'parse', 5) === 0
				&& substr_compare($methodName, 'Markers', -7) !== 0) {
				if (method_exists($this, $methodName.'Markers')) {
					$array = call_user_func(array($this, $methodName.'Markers'));
					foreach($array as $marker) {
						$markers[$marker] = $methodName;
					}
				}
			}
		}
		return $markers;
	}

	/**
	 * Prepare markers that are used in the text to parse
	 *
	 * Add all markers that are present in markdown.
	 * Check is done to avoid iterations in parseInline(), good for huge markdown files
	 * @param string $text
	 */
	protected function prepareMarkers($text): void
	{
		$this->_inlineMarkers = [];
		foreach ($this->inlineMarkers() as $marker => $method) {
			if (strpos($text, $marker) !== false) {
				$m = $marker[0];
				// put the longest marker first
				if (isset($this->_inlineMarkers[$m])) {
					reset($this->_inlineMarkers[$m]);
					if (strlen($marker) > strlen(key($this->_inlineMarkers[$m]))) {
						$this->_inlineMarkers[$m] = array_merge(
							[$marker => $method], $this->_inlineMarkers[$m]
						);
						continue;
					}
				}
				$this->_inlineMarkers[$m][$marker] = $method;
			}
		}
	}

	/**
	 * Parses inline elements of the language.
	 *
	 * @param string $text the inline text to parse.
	 * @return array
	 */
	protected function parseInline($text): array
	{
		if ($this->_depth >= $this->maximumNestingLevel) {
			// maximum depth is reached, do not parse input
			return [['text', $text]];
		}
		$this->_depth++;

		$markers = implode('', array_keys($this->_inlineMarkers));

		$paragraph = [];

		while (!empty($markers) && ($found = strpbrk($text, $markers)) !== false) {

			$pos = strpos($text, $found);

			// add the text up to next marker to the paragraph
			if ($pos !== 0) {
				$paragraph[] = ['text', substr($text, 0, $pos)];
			}
			$text = $found;

			$parsed = false;
			foreach ($this->_inlineMarkers[$text[0]] as $marker => $method) {
				if (strncmp($text, $marker, strlen($marker)) === 0) {
					// parse the marker
					array_unshift($this->context, $method);
					list($output, $offset) = $this->$method($text);
					array_shift($this->context);

					$paragraph[] = $output;
					$text = substr($text, $offset);
					$parsed = true;
					break;
				}
			}
			if (!$parsed) {
				$paragraph[] = ['text', substr($text, 0, 1)];
				$text = substr($text, 1);
			}
		}

		$paragraph[] = ['text', $text];

		$this->_depth--;

		return $paragraph;
	}

	/**
	 * Declares inline markers for the corresponding parser method.
	 *
	 * @return array
	 */
	protected function parseEscapeMarkers(): array
	{
		return array('\\');
	}

	/**
	 * Parses escaped special characters.
	 * @marker \
	 */
	protected function parseEscape($text): array
	{
		if (isset($text[1]) && in_array($text[1], $this->escapeCharacters)) {
			return [['text', $text[1]], 2];
		}
		return [['text', $text[0]], 1];
	}

	/**
	 * This function renders plain text sections in the markdown text.
	 * It can be used to work on normal text sections.
	 * E.g. to highlight keywords or do special escaping.
	 */
	protected function renderText($block): string
	{
		return $block[1];
	}
}
