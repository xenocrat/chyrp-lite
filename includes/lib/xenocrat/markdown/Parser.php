<?php
/**
 * @copyright Copyright 2014 Carsten Brandt, 2024 Daniel Pimley
 * @license https://github.com/xenocrat/chyrp-markdown/blob/master/LICENSE
 * @link https://github.com/xenocrat/chyrp-markdown#readme
 */

namespace xenocrat\markdown;
use ReflectionClass;
use ReflectionMethod;
use RuntimeException;

/**
 * A generic parser for Markdown-like languages.
 *
 * @author Carsten Brandt
 * @author Daniel Pimley
 */
abstract class Parser
{
	const VERSION_MAJOR = 3;
	const VERSION_MINOR = 1;

	/**
	 * @var integer - The maximum nesting level for language elements.
	 */
	public $maximumNestingLevel = 32;

	/**
	 * @var boolean - Throw if the maximum nesting level is exceeded.
	 */
	public $maximumNestingLevelThrow = false;

	/**
	 * @var boolean - Whether to convert all tabs into 4 spaces.
	 */
	public $convertTabsToSpaces = false;

	/**
	 * @var boolean - Whether to format markup according to HTML5 spec.
	 *
	 * Defaults to `false` which means that markup is formatted as HTML4.
	 */
	public $html5 = false;

	/**
	 * @var array - These are "escapeable" characters.
	 *
	 * When using one of these prefixed with a backslash, the character is
	 * not interpreted as markdown and will be outputted without backslash.
	 */
	protected $escapeCharacters = [
		'\\', // backslash
	];

	/**
	 * @var array - Predefined call order for block identifier methods.
	 */
	protected $blockPriorities = [];

	/**
	 * @var array - The parser's current context.
	 */
	protected $context = [];

	/**
	 * @var integer - The parser's current nesting level.
	 */
	private $_depth = 0;

	/**
	 * @var string - Identifier for this rendering context.
	 */
	private $_contextId = '';

	/**
	 * Parses the given text considering the full language.
	 *
	 * @param string $text - The text to parse.
	 * @return string - Parsed markup.
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
	 * Parses a paragraph ignoring block elements.
	 *
	 * @param string $text - The text to parse.
	 * @return string - Parsed markup.
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
	 * @param string $text - The text to parse.
	 * @return string - The pre-processed text.
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
	 * @param string $markup - Parsed markup.
	 * @return string - Post-processed markup.
	 */
	protected function postprocess($markup): string
	{
		$safeChr = "\u{FFFD}";
		$markup = rtrim($markup, "\n");
		$markup = str_replace("\0", $safeChr, $markup);
		$markup = preg_replace('/&#[Xx]?0+;/', $safeChr, $markup);
		return $markup;
	}

	/**
	 * Get the identifier for this rendering context.
	 *
	 * @return string - The identifier.
	 */
	public function getContextId(): string
	{
		return $this->_contextId;
	}

	/**
	 * Set the identifier for this rendering context.
	 *
	 * @param string $string - Identifier to set.
	 * @return string - The identifier.
	 */
	public function setContextId($string): string
	{
		$id = str_replace(
			['&', '<', '>', '"'],
			'',
			strval($string)
		);

		return $this->_contextId = $id;
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

	#---------------------------------------------
	# Block parsing
	#---------------------------------------------

	private $_blockTypes;

	/**
	 * Detect registered block types.
	 *
	 * @return array - A list of block element types available.
	 */
	protected function blockTypes(): array
	{
		if ($this->_blockTypes === null) {
			// Detect block types via "identify" methods.
			$reflection = new ReflectionClass($this);

			$this->_blockTypes = array_filter(
				array_map(
					function($method) {
						$methodName = $method->getName();
						return str_starts_with($methodName, 'identify') ?
							substr($methodName, 8) :
							false;
					},
					$reflection->getMethods(ReflectionMethod::IS_PROTECTED)
				)
			);

			// Merge the predefined call order with the array of detected methods.
			$this->_blockTypes = array_unique(
				array_merge(
					$this->blockPriorities,
					$this->_blockTypes
				),
				SORT_STRING
			);
		}

		return $this->_blockTypes;
	}

	/**
	 * Given a set of lines and an index of a current line it uses
	 * the registered block types to detect the type of this line.
	 *
	 * @param array $lines
	 * @param integer $current
	 * @return string - Name of the block type in lower case.
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
		// Consider the line a normal paragraph if no other block type matches.
		return 'paragraph';
	}

	/**
	 * Parse block elements by calling `detectLineType()` to identify them
	 * and call consume function afterwards.
	 *
	 * @param array $lines
	 * @return array
	 */
	protected function parseBlocks($lines): array
	{
		if ($this->_depth >= $this->maximumNestingLevel) {
		// Maximum depth is reached; do not parse input.
			if ($this->maximumNestingLevelThrow) {
                throw new RuntimeException(
                    'Parser exceeded maximum nesting level'
                );
			}
			return [['text', implode("\n", $lines)]];
		}

		$this->_depth++;
		$blocks = [];

		// Convert lines to blocks.
		for ($i = 0, $count = count($lines); $i < $count; $i++) {
			$line = $lines[$i];
			if ($line !== '' && rtrim($line) !== '') {
			// Skip empty lines.
				// Identify beginning of a block and parse the content.
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
	 * Parses the block at current line by identifying the block type
	 * and parsing the content.
	 *
	 * @param $lines
	 * @param $current
	 * @return array - Array of two elements:
	 * 	(array) The parsed block;
	 * 	(int) The the next line index to be parsed.
	 */
	protected function parseBlock($lines, $current): array
	{
		// Identify block type for this line.
		$blockType = $this->detectLineType($lines, $current);

		// Call consume method for the detected block type
		// to consume further lines.
		return $this->{'consume' . $blockType}($lines, $current);
	}

	/**
	 * Renders a Markdown abstract syntax tree as HTML.
	 *
	 * @param array $blocks
	 * @return string
	 */
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
	 * Consume lines for a paragraph.
	 *
	 * @param array $lines
	 * @param integer $current
	 * @return array
	 */
	protected function consumeParagraph($lines, $current): array
	{
		$content = [];
		// Consume until blank line...
		for ($i = $current, $count = count($lines); $i < $count; $i++) {
			if (ltrim($lines[$i]) !== '') {
				$content[] = trim($lines[$i]);
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
	 * Render a paragraph block.
	 *
	 * @param array $block
	 * @return string
	 */
	protected function renderParagraph($block): string
	{
		return '<p>' . $this->renderAbsy($block['content']) . "</p>\n";
	}

	#---------------------------------------------
	# Inline parsing
	#---------------------------------------------

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
	 * @return array - A map of markers to parser methods.
	 */
	protected function inlineMarkers(): array
	{
		$markers = [];
		// Detect "parse" functions.
		$reflection = new ReflectionClass($this);

		foreach($reflection->getMethods(ReflectionMethod::IS_PROTECTED) as $method) {
			$methodName = $method->getName();
			if (
				str_starts_with($methodName, 'parse')
				&& !str_ends_with($methodName, 'Markers')
			) {
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
	 * Prepare markers that are used in the text to parse.
	 *
	 * @param string $text
	 */
	protected function prepareMarkers($text): void
	{
		$this->_inlineMarkers = [];

		foreach ($this->inlineMarkers() as $marker => $method) {
			if (strpos($text, $marker) !== false) {
				$m = $marker[0];
				// Put the longest marker first.
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
	 * @param string $text - The inline text to parse.
	 * @return array
	 */
	protected function parseInline($text): array
	{
		if ($this->_depth >= $this->maximumNestingLevel) {
		// Maximum depth is reached; do not parse input.
			if ($this->maximumNestingLevelThrow) {
                throw new RuntimeException(
                    'Parser exceeded maximum nesting level'
                );
			}
			return [['text', $text]];
		}

		$this->_depth++;
		$markers = implode('', array_keys($this->_inlineMarkers));
		$paragraph = [];

		while (!empty($markers) && ($found = strpbrk($text, $markers)) !== false) {
			$pos = strpos($text, $found);
			// Add the text up to next marker to the paragraph.
			if ($pos !== 0) {
				$paragraph[] = ['text', substr($text, 0, $pos)];
			}

			$text = $found;
			$parsed = false;

			foreach ($this->_inlineMarkers[$text[0]] as $marker => $method) {
				if (strncmp($text, $marker, strlen($marker)) === 0) {
					// Parse the marker.
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
	 *
	 * @marker \
	 */
	protected function parseEscape($text): array
	{
		if (
			isset($text[1])
			&& in_array($text[1], $this->escapeCharacters)
		) {
			$chr = $this->escapeHtmlEntities($text[1], ENT_COMPAT);
			return [['text', $chr], 2];
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

	/**
	 * Add backslash to escapeable characters in text.
	 *
	 * @param string $text
	 * @return string
	 */
	protected function escapeBackslash($text): string
	{
		$strtr = [];
		foreach($this->escapeCharacters as $chr) {
			$strtr[$chr] = "\\$chr";
		}
		return strtr($text, $strtr);
	}

	/**
	 * Remove backslash from escaped characters in text.
	 *
	 * @param string $text
	 * @return string
	 */
	protected function unEscapeBackslash($text): string
	{
		$strtr = [];
		foreach($this->escapeCharacters as $chr) {
			$strtr["\\$chr"] = $chr;
		}
		return strtr($text, $strtr);
	}

	/**
	 * Encode HTML special characters as HTML entities.
	 *
	 * @param string $text
	 * @param integer $flags
	 * @return string
	 * @see https://www.php.net/manual/en/function.htmlspecialchars
	 */
	protected function escapeHtmlEntities($text, $flags = 0): string
	{
		$ent = $this->html5 ? ENT_HTML5 : ENT_HTML401;
		$text = htmlspecialchars($text, $flags | $ent, 'UTF-8');
		return $text;
	}

	/**
	 * Decode HTML entities to corresponding characters.
	 *
	 * @param string $text
	 * @param integer $flags
	 * @return string
	 * @see https://www.php.net/manual/en/function.html-entity-decode
	 */
	protected function unEscapeHtmlEntities($text, $flags = 0): string
	{
		$ent = $this->html5 ? ENT_HTML5 : ENT_HTML401;
		$text = html_entity_decode($text, $flags | $ent, 'UTF-8');
		return $text;
	}
}
