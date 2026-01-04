<?php
/**
 * @copyright Copyright 2014 Carsten Brandt, 2024-2026 Daniel Pimley
 * @license https://github.com/xenocrat/chyrp-markdown/blob/master/LICENSE
 * @link https://github.com/xenocrat/chyrp-markdown#readme
 */

namespace xenocrat\markdown\block;

/**
 * Adds table blocks.
 */
trait TableTrait
{
	/**
	 * Identify a line as the beginning of a table block.
	 */
	protected function identifyTable($line, $lines, $current): bool
	{
		return (
			strpos($line, '|') !== false && isset($lines[$current + 1])
			&& preg_match(
				'/^\s*\|?(\s*:?-[\-\s]*:?\s*\|?)*\s*$/',
				$lines[$current + 1]
			)
			&& strpos($lines[$current + 1], '|') !== false
			// Attempt to detect a mismatch in the
			// number of header and delimiter columns.
			&& (
				preg_match_all('/(?<!^|\\\\)\|(?!$)/', $line)
				===
				preg_match_all('/(?<!^|\\\\)\|(?!$)/', $lines[$current + 1])
			)
		);
	}

	/**
	 * Consume lines for a table.
	 */
	protected function consumeTable($lines, $current): array
	{
		$block = [
			'table',
			'cols' => [],
			'rows' => [],
		];

		// Consume until blank line...
		for ($i = $current, $count = count($lines); $i < $count; $i++) {
			$line = trim($lines[$i]);
			// Extract alignment from second line.
			if ($i == $current + 1) {
				$cols = explode('|', trim($line, ' |'));
				foreach($cols as $col) {
					$col = trim($col);
					if (empty($col)) {
						$block['cols'][] = '';
						continue;
					}
					$l = ($col[0] === ':');
					$r = str_ends_with($col, ':');
					if ($l && $r) {
						$block['cols'][] = 'center';
					} elseif ($l) {
						$block['cols'][] = 'left';
					} elseif ($r) {
						$block['cols'][] = 'right';
					} else {
						$block['cols'][] = '';
					}
				}
				continue;
			}
			if (
				// Blank line breaks the table.
				$line === ''
				|| (
				// Once iteration is beyond the header and delimiter rows,
				// detecting a non-paragraph block marker breaks the table.
					$i > $current + 1
					&& $this->detectLineType($lines, $i) !== 'paragraph'
				)
			) {
				break;
			}
			if ($line[0] === '|') {
				$line = substr($line, 1);
			}
			if (
				str_ends_with($line, '|')
				&& (
					!str_ends_with($line, '\\|')
					|| str_ends_with($line, '\\\\|')
				)
			) {
				$line = substr($line, 0, -1);
			}

			array_unshift($this->context, 'table');
			$row = $this->parseInline(trim($line, ' '));
			array_shift($this->context);

			$r = count($block['rows']);
			$c = 0;
			$block['rows'][] = [];

			foreach ($row as $absy) {
				if (!isset($block['rows'][$r][$c])) {
					$block['rows'][$r][] = [];
				}
				if ($absy[0] === 'tableBoundary') {
					$c++;
				} else {
					$block['rows'][$r][$c][] = $absy;
				}
			}
		}

		return [$block, --$i];
	}

	/**
	 * Render a table block.
	 */
	protected function renderTable($block): string
	{
		$head = '';
		$body = '';
		$cols = $block['cols'];
		$colCount = count($block['cols']);
		$first = true;

		foreach($block['rows'] as $row) {
			$cellTag = $first ? 'th' : 'td';
			$tds = '';
			foreach ($row as $c => $cell) {
				if ($c < $colCount) {
					$align = empty($cols[$c]) ?
						'' :
						' align="' . $cols[$c] . '"' ;

					$tds .= "<$cellTag$align>"
						. trim($this->renderAbsy($cell))
						. "</$cellTag>\n";
				}
			}
			for ($i = count($row); $i < $colCount; $i++) { 
				$tds .= "<$cellTag></$cellTag$align>\n";
			}
			if ($first) {
				$head .= "<tr>\n$tds</tr>\n";
			} else {
				$body .= "<tr>\n$tds</tr>\n";
			}
			$first = false;
		}

		return $this->composeTable($head, $body);
	}

	/**
	 * This method composes a table from parsed body and head HTML.
	 *
	 * @param string $head - Table head HTML.
	 * @param string $body - Table body HTML.
	 * @return string - The complete table HTML.
	 * @since 1.2.0
	 */
	protected function composeTable($head, $body): string
	{
		$table = "<table>\n";
		if ($head !== '') {
			$table .= "<thead>\n$head</thead>\n";
		}
		if ($body !== '') {
			$table .= "<tbody>\n$body</tbody>\n";
		}
		$table .= "</table>\n";
		return $table;
	}

	protected function parseTdMarkers(): array
	{
		return array('|');
	}

	/**
	 * Parses table data cells.
	 *
	 * @marker |
	 */
	protected function parseTd($markdown): array
	{
		if (isset($this->context[1]) && $this->context[1] === 'table') {
			return [
				['tableBoundary'],
				isset($markdown[1]) && $markdown[1] === ' ' ? 2 : 1
			];
		}
		return [['text', $markdown[0]], 1];
	}

	abstract protected function renderText($block);
	abstract protected function parseInline($text);
	abstract protected function renderAbsy($absy);
	abstract protected function detectLineType($lines, $current);
}
