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
			str_contains($line, '|')
			&& isset($lines[$current + 1])
			&& str_contains($lines[$current + 1], '|')
			&& preg_match(
				'/^\s*\|?(?:\s*:?-[\-\s]*:?\s*\|?)*\s*$/',
				$lines[$current + 1]
			)
			&& (
				preg_match_all(
					'/(?<!^|\\\\)\|(?!$)/',
					str_replace(
						'\\\\',
						'\\\\'.chr(31),
						$line
					)
				)
				===
				preg_match_all(
					'/(?<!^)\|(?!$)/',
					$lines[$current + 1]
				)
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
			$line = str_replace(
				'\\\\',
				'\\\\'.chr(31),
				trim($lines[$i])
			);
			// Extract alignment from second line.
			if ($i === $current + 1) {
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
			if (str_starts_with($line, '|')) {
				$line = substr($line, 1);
			}
			if (
				str_ends_with($line, '|')
				&& !str_ends_with($line, '\\|')
			) {
				$line = substr($line, 0, -1);
			}
			$row = preg_split('/(?<!\\\\)\|/', $line);
			$r = count($block['rows']);
			foreach ($row as $c => $content) {
				if ($i !== $current && !isset($cols[$c])) {
					break;
				}
				$content = str_replace(
					'\|',
					'|',
					$content
				);
				$content = str_replace(
					'\\\\'.chr(31),
					'\\\\',
					$content
				);
				$block['rows'][$r][$c] = $this->parseInline($content);
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
		foreach($block['rows'] as $r => $row) {
			$tag = ($r === 0) ? 'th' : 'td';
			$cells = '';
			for ($c = 0; $c < $colCount; $c++) {
				$align = empty($cols[$c]) ?
					'' :
					' align="' . $cols[$c] . '"';

				$content = empty($row[$c]) ?
					'' :
					trim($this->renderAbsy($row[$c]));

				$cells .= "<{$tag}{$align}>{$content}</{$tag}>\n";
			}
			if ($r === 0) {
				$head .= "<tr>\n{$cells}</tr>\n";
			} else {
				$body .= "<tr>\n{$cells}</tr>\n";
			}
		}
		return "<table>\n<thead>\n{$head}</thead>\n"
			. ($body === '' ? '' : "<tbody>\n{$body}</tbody>\n")
			. "</table>\n";
	}

	abstract protected function renderText($block);
	abstract protected function parseInline($text);
	abstract protected function renderAbsy($absy);
	abstract protected function detectLineType($lines, $current);
}
