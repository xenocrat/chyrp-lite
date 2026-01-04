<?php
/**
 * @copyright Copyright 2024-2026 Daniel Pimley
 * @license https://github.com/xenocrat/chyrp-markdown/blob/master/LICENSE
 * @link https://github.com/xenocrat/chyrp-markdown#readme
 */

namespace xenocrat\markdown\inline;

/**
 * Adds checkbox inline elements.
 */
trait CheckboxTrait
{
	/**
	 * @var bool - Render inputs instead of emoji.
	 */
	public $renderCheckboxInputs = false;

	protected function parseCheckboxMarkers(): array
	{
		return array('[ ]', '[x]', '[X]', '[~]');
	}

	/**
	 * Parses the checkbox feature.
	 *
	 * @marker [ ]
	 * @marker [x]
	 * @marker [X]
	 * @marker [~]
	 */
	protected function parseCheckbox($markdown): array
	{
		return [
			[
				'checkbox',
				'incomplete' => ($markdown[1] === ' '),
				'inapplicable' => ($markdown[1] === '~')
			],
			3
		];
	}

	protected function renderCheckbox($block): string
	{
		if ($this->renderCheckboxInputs) {
			return '<input'
				. ($block['incomplete'] ? '' : ' checked=""')
				. ' disabled=""'
				. ' type="checkbox"'
				. ($this->html5 ? '>' : ' />');
		}

		if ($block['inapplicable']) {
			return "\u{1F6AB}";
		}

		return $block['incomplete'] ? "\u{274E}" : "\u{2705}";
	}
}
