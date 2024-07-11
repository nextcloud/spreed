/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { onKeyStroke } from '@vueuse/core'
import type { ObjectDirective } from 'vue'

type BindingValueFunction = (event: KeyboardEvent) => void

const disableKeyboardShortcuts: boolean = OCP.Accessibility.disableKeyboardShortcuts()

/**
 * Check if event target (active element) is interactive and should not trigger the directive callback
 * @param event keyboard event
 */
export function shouldPreventCallback(event: KeyboardEvent): boolean {
	/** Abort the directive if active element is an input, textarea or contenteditable */
	if (event.target instanceof HTMLInputElement
		/**
		 * TODO discuss if we should abort on another interactive elements
		 * || event.target instanceof HTMLButtonElement
		 * || event.target instanceof HTMLAnchorElement
		 * || event.target instanceof HTMLSelectElement
		 * || (event.target as HTMLElement)?.hasAttribute('tabindex')
		 */
		|| event.target instanceof HTMLTextAreaElement
		|| (event.target as HTMLElement)?.isContentEditable) {
		return true
	}
	/** Abort the directive if any modal/dialog opened */
	return document.getElementsByClassName('modal-mask').length !== 0
}

const eventHandler = (callback: BindingValueFunction, modifiers: Record<string, boolean|undefined>) => (event: KeyboardEvent) => {
	if (!!modifiers.ctrl !== event.ctrlKey) {
		// Ctrl is required and not pressed, or the opposite
	} else if (disableKeyboardShortcuts) {
		// Keyboard shortcuts are disabled
	} else if (shouldPreventCallback(event)) {
		// Keyboard shortcuts are disabled, because active element assumes input
	} else {
		if (modifiers.prevent) {
			event.preventDefault()
		}
		if (modifiers.stop) {
			event.stopPropagation()
		}
		callback(event)
	}
}

export const KeyStroke: ObjectDirective<HTMLElement, BindingValueFunction> = {
	bind(el, binding) {
		const keys = binding.arg?.replace('space', ' ').split(',') ?? true
		onKeyStroke(keys, eventHandler(binding.value, binding.modifiers), {
			eventName: 'keydown',
			dedupe: true,
			target: disableKeyboardShortcuts ? null : document
		})
		if (binding.modifiers.push) {
			onKeyStroke(keys, eventHandler(binding.value, binding.modifiers), {
				eventName: 'keyup',
				target: disableKeyboardShortcuts ? null : document
			})
		}
	},
}
