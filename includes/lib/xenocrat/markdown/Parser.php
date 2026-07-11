<?php
/**
 * @copyright Copyright 2014 Carsten Brandt, 2024-2026 Daniel Pimley
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
	const VERSION_MAJOR = 4;
	const VERSION_MINOR = 16;
	const VERSION_PATCH = 0;

	/**
	 * @var integer - The maximum nesting level for language elements.
	 */
	public $maximumNestingLevel = 32;

	/**
	 * @var boolean - Throw if the maximum nesting level is exceeded.
	 */
	public $maximumNestingLevelThrow = false;

	/**
	 * @var float - The maximum execution time for parsing in seconds.
	 */
	public $maximumExecutionTime = 10.0;

	/**
	 * @var boolean - Throw if the maximum execution time is exceeded.
	 */
	public $maximumExecutionTimeThrow = false;

	/**
	 * @var boolean - Whether to convert all tabs into spaces.
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
	 * @var array - The parser's context stack:
	 * 
	 * An array of strings in the form identifyFoo, parseBar, consumeBaz, renderBoo.
	 */
	protected $context = [];

	/**
	 * @var integer - The parser's current nesting level.
	 */
	private $_depth = 0;

	/**
	 * @var float - The execution start time: Unix timestamp with microseconds.
	 */
	private $_timer = 0.0;

	/**
	 * @var array - Prioritized list of block identifier methods.
	 */
	private $_blockTypes;

	/**
	 * @var array - Map of inline markers and corresponding parser methods.
	 */
	private $_inlineMarkers = [];

	/**
	 * @var string - Identifier for this rendering context.
	 * 
	 * Set this to output unique element IDs when traits render HTML anchors etc.
	 */
	private $_contextId = '';

	/**
	 * Parses the given text considering the full language.
	 *
	 * @param string $text - The text to parse.
	 * @return string - Parsed markup.
	 */
	public function parse(
		$text
	): string {
		$this->prepare();

		if (ltrim($text) === '') {
			return '';
		}

		$text = $this->preprocess($text);

		$this->resetTimer();
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
	public function parseParagraph(
		$text
	): string {
		$this->prepare();

		if (ltrim($text) === '') {
			return '';
		}

		$text = $this->preprocess($text);

		$this->resetTimer();
		$this->prepareMarkers($text);

		$absy = $this->parseInline($text);
		$markup = $this->renderAbsy($absy);
		$markup = $this->postprocess($markup);

		$this->cleanup();
		return $markup;
	}

	/**
	 * Get the identifier for this rendering context.
	 *
	 * @return string - The identifier.
	 */
	public function getContextId(
	): string {
		return $this->_contextId;
	}

	/**
	 * Set the identifier for this rendering context.
	 *
	 * @param string $string - Identifier to set.
	 * @return string - The newly set identifier.
	 */
	public function setContextId(
		$string
	): string {
		$id = str_replace(
			['&', '<', '>', '"', ' '],
			'',
			strval($string)
		);

		return $this->_contextId = $id;
	}

	/**
	 * Pre-processes text before parsing.
	 *
	 * @param string $text - The text to parse.
	 * @return string - The pre-processed text.
	 */
	protected function preprocess(
		$text
	): string {
		$text = str_replace(["\r\n", "\n\r", "\r"], "\n", $text);

		if ($this->convertTabsToSpaces) {
			$text = $this->expandTabs($text);
		}

		return $text;
	}

	/**
	 * Post-processes markup after parsing.
	 *
	 * @param string $markup - Parsed markup.
	 * @return string - Post-processed markup.
	 */
	protected function postprocess(
		$markup
	): string {
		$safeChr = "\u{FFFD}";
		$markup = rtrim($markup, "\n");
		$markup = str_replace("\0", $safeChr, $markup);
		$markup = preg_replace('/&\#[Xx]?0+;/', $safeChr, $markup);
		return $markup;
	}

	/**
	 * Resets the execution timer.
	 */
	protected function resetTimer(
	): void {
		$this->_timer = microtime(true);
	}

	/**
	 * Returns the elapsed execution time.
	 *
	 * @return float - The elapsed time in seconds.
	 */
	protected function checkTimer(
	): float {
		return microtime(true) - $this->_timer;
	}

	/**
	 * This method will be called before `parse()` and `parseParagraph()`.
	 * You can override it to do some initialization work.
	 */
	protected function prepare(
	): void {
	}

	/**
	 * This method will be called after `parse()` and `parseParagraph()`.
	 * You can override it to do cleanup.
	 */
	protected function cleanup(
	): void {
	}

	#---------------------------------------------
	# Block parsing
	#---------------------------------------------

	/**
	 * Detect registered block types.
	 *
	 * @return array - A list of block element types available.
	 */
	protected function blockTypes(
	): array {
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
	 * @param array $lines - Array of text separated into lines.
	 * @param integer $current - Index of the current line.
	 * @return string - Name of the block type in lower case.
	 */
	protected function detectLineType(
		$lines,
		$current
	): string {
		$line = $lines[$current];
		$found = false;
		$blockTypes = $this->blockTypes();

		foreach($blockTypes as $blockType) {
			$blockType = ucfirst($blockType);
			array_unshift($this->context, 'identify' . $blockType);
			if ($this->{'identify' . $blockType}($line, $lines, $current)) {
				$found = true;
			}
			array_shift($this->context);
			if ($found === true) {
				return lcfirst($blockType);
			}
		}

		// Consider the line a normal paragraph if no other block type matches.
		return 'paragraph';
	}

	/**
	 * Parse block elements by calling `detectLineType()` to identify them
	 * and call consume function afterwards.
	 *
	 * @param array $lines - Array of text separated into lines.
	 * @param int &$blanks - Increments for every blank line outside a block.
	 * @return array
	 */
	protected function parseBlocks(
		$lines,
		&$blanks = 0
	): array {
		if ($this->_depth >= $this->maximumNestingLevel) {
		// Exceeded maximum depth; do not parse input.
			if ($this->maximumNestingLevelThrow) {
                throw new RuntimeException(
                    'Parser exceeded maximum nesting level'
                );
			}
			return [['text', implode("\n", $lines)]];
		}

		if ($this->checkTimer() > $this->maximumExecutionTime) {
		// Exceeded maximum execution time; do not parse input.
			if ($this->maximumExecutionTimeThrow) {
                throw new RuntimeException(
                    'Parser exceeded maximum execution time'
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
			} else {
				$blanks++;
			}
		}

		$this->_depth--;
		return $blocks;
	}

	/**
	 * Parses the block at current line by identifying the block type
	 * and parsing the content.
	 *
	 * @param $lines - Array of text separated into lines.
	 * @param $current - Index of the current line.
	 * @return array - Array of two elements:
	 * 					(array) The parsed block;
	 * 					(int) The the next line index to be parsed.
	 */
	protected function parseBlock(
		$lines,
		$current
	): array {
		// Identify block type for this line.
		$blockType = ucfirst(
			$this->detectLineType($lines, $current)
		);

		// Call consume method for the detected block type
		// to consume further lines.
		array_unshift($this->context, 'consume' . $blockType);
		$consumed = $this->{'consume' . $blockType}($lines, $current);
		array_shift($this->context);
		return $consumed;
	}

	/**
	 * Renders a Markdown abstract syntax tree as HTML.
	 *
	 * @param array $blocks - Array of blocks to render.
	 * @return string
	 */
	protected function renderAbsy(
		$blocks
	): string {
		$output = '';
		foreach ($blocks as $block) {
			$blockType = ucfirst($block[0]);
			array_unshift($this->context, 'render' . $blockType);
			$output .= $this->{'render' . $blockType}($block);
			array_shift($this->context);
		}
		return $output;
	}

	/**
	 * Consume lines for a paragraph.
	 *
	 * @param array $lines - Array of text separated into lines.
	 * @param integer $current - Index of the current line.
	 * @return array
	 */
	protected function consumeParagraph(
		$lines,
		$current
	): array {
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
	 * @param array $block - The block to render.
	 * @return string
	 */
	protected function renderParagraph(
		$block
	): string {
		return '<p>' . $this->renderAbsy($block['content']) . "</p>\n";
	}

	#---------------------------------------------
	# Inline parsing
	#---------------------------------------------

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
	protected function inlineMarkers(
	): array {
		$markers = [];
		// Detect "parse" functions.
		$reflection = new ReflectionClass($this);

		foreach(
			$reflection->getMethods(
				ReflectionMethod::IS_PROTECTED
			) as $method
		) {
			$methodName = $method->getName();

			if (
				str_starts_with($methodName, 'parse')
				&& !str_ends_with($methodName, 'Markers')
			) {
				if (method_exists($this, $methodName.'Markers')) {
					$array = call_user_func(
						array($this, $methodName.'Markers')
					);
					foreach($array as $marker) {
						$markers[$marker] = 'parse' . ucfirst(
							substr($methodName, 5)
						);
					}
				}
			}
		}

		return $markers;
	}

	/**
	 * Prepare markers that are used in the text to parse.
	 *
	 * @param string $text - The inline text to parse.
	 */
	protected function prepareMarkers(
		$text
	): void {
		$this->_inlineMarkers = [];

		foreach ($this->inlineMarkers() as $marker => $method) {
			if (strpos($text, $marker) !== false) {
				$m = $marker[0];

				// Put the longest marker first.
				if (isset($this->_inlineMarkers[$m])) {
					reset($this->_inlineMarkers[$m]);
					if (
						strlen($marker) >=
						strlen(key($this->_inlineMarkers[$m]))
					) {
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
	 * @param string $preceding - Preceding inline text.
	 * @return array
	 */
	protected function parseInline(
		$text,
		$preceding = ''
	): array {
		if ($this->_depth >= $this->maximumNestingLevel) {
		// Exceeded maximum depth; do not parse input.
			if ($this->maximumNestingLevelThrow) {
                throw new RuntimeException(
                    'Parser exceeded maximum nesting level'
                );
			}
			return [['text', $text]];
		}

		if ($this->checkTimer() > $this->maximumExecutionTime) {
		// Exceeded maximum execution time; do not parse input.
			if ($this->maximumExecutionTimeThrow) {
                throw new RuntimeException(
                    'Parser exceeded maximum execution time'
                );
			}
			return [['text', $text]];
		}

		$this->_depth++;
		$markers = implode('', array_keys($this->_inlineMarkers));
		$paragraph = [];

		if (!empty($markers)) {
			while (($found = strpbrk($text, $markers)) !== false) {
				$pos = strpos($text, $found);

				// Add the text up to next marker to the paragraph.
				if ($pos !== 0) {
					$substr = substr($text, 0, $pos);
					$preceding .= $substr;
					$paragraph[] = ['text', $substr];
				}

				$text = $found;
				$parsed = false;

				foreach (
					$this->_inlineMarkers[$text[0]] as $marker => $method
				) {
					if (str_starts_with($text, $marker)) {
						// Parse the marker.
						array_unshift($this->context, $method);
						list($output, $offset) = $this->$method($text, $preceding);
						array_shift($this->context);

						$preceding .= substr($text, 0, $offset);
						$text = substr($text, $offset);
						$paragraph[] = $output;
						$parsed = true;
						break;
					}
				}

				if (!$parsed) {
					$substr = substr($text, 0, 1);
					$preceding .= $substr;
					$paragraph[] = ['text', $substr];
					$text = substr($text, 1);
				}
			}
		}

		$paragraph[] = ['text', $text];
		$this->_depth--;
		return $paragraph;
	}

	/**
	 * Detects if any inline elements overrun a substring.
	 *
	 * @param string $text - The inline text to search.
	 * @param integer $length - Length of the substring.
	 * @param array $elements - Inline element names to test.
	 * @return boolean
	 */
	protected function detectInlineOverrun(
		$text,
		$length,
		$elements
	): bool {
		foreach ($elements as $element) {
			if (method_exists($this, 'parse'.$element.'Markers')) {
				$markers = call_user_func(
					array($this, 'parse'.$element.'Markers')
				);

				foreach ($markers as $marker) {
					$pos = 0;
					do {
						$pos = strpos($text, $marker, $pos);
						if ($pos !== false && $pos < $length) {
							$arr = call_user_func(
								array($this, 'parse'.$element),
								substr($text, $pos)
							);

							if (
								$arr[0][0] !== 'text'
								&& $arr[1] > ($length - $pos)
							) {
								return true;
							} else {
								$pos += $arr[1];
							}
						}
					} while ($pos !== false && $pos < $length);
				}
			}
		}

		return false;
	}

	/**
	 * Declares inline markers for the corresponding parser method.
	 *
	 * @return array
	 */
	protected function parseEscapeMarkers(
	): array {
		return array('\\');
	}

	/**
	 * Parses escaped special characters.
	 *
	 * @marker \
	 */
	protected function parseEscape(
		$text
	): array {
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
	protected function renderText(
		$block
	): string {
		return $block[1];
	}

	/**
	 * Add backslash to escapeable characters in text.
	 *
	 * @param string $text - The string to be processed.
	 * @return string
	 */
	protected function escapeBackslash(
		$text
	): string {
		$strtr = [];

		foreach($this->escapeCharacters as $chr) {
			$strtr[$chr] = "\\$chr";
		}

		return strtr($text, $strtr);
	}

	/**
	 * Remove backslash from escaped characters in text.
	 *
	 * @param string $text - The string to be processed.
	 * @return string
	 */
	protected function unEscapeBackslash(
		$text
	): string {
		$strtr = [];

		foreach($this->escapeCharacters as $chr) {
			$strtr["\\$chr"] = $chr;
		}

		return strtr($text, $strtr);
	}

	/**
	 * Encode HTML special characters as HTML entities.
	 *
	 * @param string $text - The string to be encoded.
	 * @param integer $flags - Flags for <htmlspecialchars>.
	 * @return string
	 * @see https://www.php.net/manual/en/function.htmlspecialchars
	 */
	protected function escapeHtmlEntities(
		$text,
		$flags = 0
	): string {
		$ent = $this->html5 ? ENT_HTML5 : ENT_HTML401;
		$text = htmlspecialchars($text, $flags | $ent, 'UTF-8');
		return $text;
	}

	/**
	 * Decode HTML entities to corresponding characters.
	 *
	 * @param string $text - The string to be decoded.
	 * @param integer $flags - Flags for <html_entity_decode>.
	 * @return string
	 * @see https://www.php.net/manual/en/function.html-entity-decode
	 */
	protected function unEscapeHtmlEntities(
		$text,
		$flags = 0
	): string {
		$ent = $this->html5 ? ENT_HTML5 : ENT_HTML401;
		$text = html_entity_decode($text, $flags | $ent, 'UTF-8');
		return $text;
	}

	/**
	 * Count the length of a UTF-8 encoded string.
	 *
	 * @param string $text - The string to be counted.
	 * @return int
	 * @see https://datatracker.ietf.org/doc/html/rfc3629
	 */
	protected function utf8Strlen(
		$text
	): int {
		if (function_exists('mb_strlen')) {
			return mb_strlen($text, 'UTF-8');
		}

		$len = strlen($text);
		$pos = 0;
		$count = 0;

		while ($pos < $len) {
			$ord = ord($text[$pos]);

			if ($ord < 128) {
				$pos++;
				$count++;
			} elseif ($ord >> 3 === 30) {
				$pos+= 4;
				$count++;
			} elseif ($ord >> 4 === 14) {
				$pos+= 3;
				$count++;
			} elseif ($ord >> 5 === 6) {
				$pos+= 2;
				$count++;
			} else {
				// Unexpected continuation byte: move on.
				$pos++;
			}
		}

		return $count;
	}

	/**
	 * Expand tabs into 1-4 occurrences of a replacement character.
	 *
	 * @param string $text - The string to be processed.
	 * @param string $chr - The replacement char to use for expansion.
	 * @return string
	 */
	protected function expandTabs(
		$text,
		$chr = ' '
	): string {
		if ($text === '') {
			return '';
		}

		$expanded = '';
		$lines = preg_split(
			"/(\n)/",
			$text,
			-1,
			PREG_SPLIT_DELIM_CAPTURE
		);

		foreach ($lines as $line) {
			$output = '';
			$chunks = preg_split(
				"/(\t)/",
				$line,
				-1,
				PREG_SPLIT_DELIM_CAPTURE
			);

			foreach ($chunks as $chunk) {
				if ($chunk === "\t") {
					$length = $this->utf8Strlen($output);
					$output .= str_repeat(
						$chr,
						4 - ($length % 4)
					);
				} else {
					$output .= $chunk;
				}
			}

			$expanded .= $output;
		}

		return $expanded;
	}

	/**
	 * Collapse replacement characters into tabs, maintain initial indent.
	 *
	 * @param string $text - The string to be processed.
	 * @param string $chr - The replacement char used to expand the tabs.
	 * @return string
	 */
	protected function collapseTabs(
		$text,
		$chr = ' '
	): string {
		if ($text === '') {
			return '';
		}

		$c = preg_quote($chr, '/');
		$collapsed = '';
		$lines = preg_split(
			"/(\n)/",
			$text,
			-1,
			PREG_SPLIT_DELIM_CAPTURE
		);

		foreach ($lines as $line) {
			$length = strlen(
				preg_replace(
					"/^([$c ]*).*$/u", '$1',
					$line
				)
			);
			$indent = substr($line, 0, $length);
			$output = substr($line, $length);
			$indent = strtr(
				$indent,
				[
					str_repeat($chr, 4) => "\t",
					$chr => ' '
				]
			);
			$output = preg_replace(
				"/$c{1,4}/u",
				"\t",
				$output
			);
			$collapsed .= $indent . $output;
		}

		return $collapsed;
	}
}
