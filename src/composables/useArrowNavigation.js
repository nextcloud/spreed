/*
 * @copyright Copyright (c) 2023 Maksim Sukharev <antreesy.web@gmail.com>
 *
 * @author Maksim Sukharev <antreesy.web@gmail.com>
 *
 * @license AGPL-3.0-or-later
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

import { computed, onMounted, ref, unref } from 'vue'

/**
 * Mount navigation according to https://www.w3.org/WAI/GL/wiki/Using_ARIA_menus
 * Item elements should have:
 * - specific valid CSS selector (tag, class or another attribute)
 * - unique "data-nav-id" attribute (on element or its parent, if it's not possible to pass it through the wrapper)
 *
 * Controls:
 * - ArrowDown or ArrowUp keys - to move through the itemElements list
 * - Enter key - to focus first element and click it
 *   (if confirmEnter = true) first Enter keydown to focus first element, second - to click selected
 * - Escape key - to return focus to the default element, if one of the items is focused already
 * - Backspace key - to return focus to the default element, if one of the items is focused already
 *
 * @param {import('vue').Ref | HTMLElement} listElementRef component ref to mount navigation
 * @param {import('vue').Ref} defaultElementRef component ref to return focus to // Vue component
 * @param {string} selector native selector of elements to look for
 * @param {object} options navigation options
 * @param {boolean} [options.confirmEnter=false] flag to confirm Enter click
 */
export function useArrowNavigation(listElementRef, defaultElementRef, selector, options = { confirmEnter: false }) {
	const listRef = ref(null)
	const defaultRef = ref(null)

	/**
	 * @constant
	 * @type {import('vue').Ref<HTMLElement[]>}
	 */
	const itemElements = ref([])
	const itemElementsIdMap = computed(() => itemElements.value.map(item => {
		return item.getAttribute('data-nav-id') || item.parentElement.getAttribute('data-nav-id')
	}))
	const itemSelector = ref(selector)

	const focusedIndex = ref(null)
	const isConfirmationEnabled = ref(null)

	// Set focused index according to selected element
	const handleFocusEvent = (event) => {
		const newIndex = itemElementsIdMap.value.indexOf(event.target?.getAttribute('data-nav-id'))

		// Quit if triggered by arrow navigation as already handled
		// or if using Tab key to navigate, and going through NcActions
		if (focusedIndex.value !== newIndex && newIndex !== -1) {
			focusedIndex.value = newIndex
		}
	}

	// Reset focused index if focus moved out of navigation area or moved to the defaultRef
	const handleBlurEvent = (event) => {
		if (!listRef.value.contains(event.relatedTarget)
			|| defaultRef.value?.$el.contains(event.relatedTarget)
			|| defaultRef.value.contains?.(event.relatedTarget)) {
			focusedIndex.value = null
		}
	}

	// Add event listeners for navigation list and set a default focus element
	onMounted(() => {
		// depending on ref, listElementRef could be either a component or a DOM element
		listRef.value = unref(listElementRef)?.$el ?? unref(listElementRef)
		defaultRef.value = unref(defaultElementRef)
		isConfirmationEnabled.value = options.confirmEnter

		listRef.value.addEventListener('keydown', (event) => {
			if (itemElementsIdMap.value?.length) {
				if (event.key === 'ArrowDown') {
					focusNextElement(event)
				} else if (event.key === 'ArrowUp') {
					focusPrevElement(event)
				} else if (event.key === 'Enter') {
					focusFirstElementIfNotFocused(event)
				} else if (event.key === 'Escape' || event.key === 'Backspace') {
					focusDefaultElement(event)
				}
			}
		})
	})

	/**
	 * Update list of navigate-able elements specified by selector.
	 * Put a listener for focus/blur events on navigation area
	 */
	function initializeNavigation() {
		itemElements.value = Array.from(listRef.value.querySelectorAll(itemSelector.value))
		focusedIndex.value = null

		listRef.value.addEventListener('focus', handleFocusEvent, true)
		listRef.value.addEventListener('blur', handleBlurEvent, true)
	}

	/**
	 * Remove listeners from navigation area, reset list of elements
	 * (to made navigation unavailable during fetching results)
	 */
	function resetNavigation() {
		itemElements.value = []

		listRef.value.removeEventListener('focus', handleFocusEvent, true)
		listRef.value.removeEventListener('blur', handleBlurEvent, true)
	}

	/**
	 * Focus natively the DOM element specified by index
	 *
	 * @param {object} index the item index
	 */
	function nativelyFocusElement(index) {
		focusedIndex.value = index
		itemElements.value[index].focus()
	}

	/**
	 * Focus the default component ('focus' method should be exposed)
	 *
	 * @param {Event} event Keydown event
	 */
	function focusDefaultElement(event) {
		if (focusedIndex.value !== null) {
			event.preventDefault()
			event.stopImmediatePropagation()
			focusedIndex.value = null
			// TODO setTimeout hacks NcModal behaviour, which removes focus-trap
			// and focuses last clicked element before Modal (NcActions in case of NewConversationDialog)
			setTimeout(() => {
				defaultRef.value.focus()
			}, 0)
		}
	}

	/**
	 * Focus the first element if not focused yet, otherwise proceed
	 *
	 * @param {Event} [event] Keydown event to prevent, if defined and not focused yet
	 *
	 * @return {boolean} If first element was focused or not
	 */
	function focusFirstElementIfNotFocused(event) {
		const isFirstElementNotFocused = focusedIndex.value === null
		if (isFirstElementNotFocused) {
			event?.preventDefault()
			nativelyFocusElement(0)

			// if confirmEnter = false, first Enter keydown clicks on item, otherwise only focuses it
			// Additionally check whether the Element is still in the DOM
			if (!isConfirmationEnabled.value && event?.key === 'Enter'
				&& listRef.value.contains(itemElements.value[0])) {
				itemElements.value[0].click()
			}
		}
		return isFirstElementNotFocused
	}

	/**
	 * Focus the next element
	 *
	 * @param {Event} event Keydown event
	 */
	function focusNextElement(event) {
		event.preventDefault()
		if (focusFirstElementIfNotFocused()) {
			return
		}

		if (focusedIndex.value < itemElementsIdMap.value.length - 1) {
			nativelyFocusElement(focusedIndex.value + 1)
		} else {
			nativelyFocusElement(0)
		}
	}

	/**
	 * Focus the previous element
	 *
	 * @param {Event} event Keydown event
	 */
	function focusPrevElement(event) {
		event.preventDefault()
		if (focusFirstElementIfNotFocused()) {
			return
		}

		if (focusedIndex.value > 0) {
			nativelyFocusElement(focusedIndex.value - 1)
		} else {
			nativelyFocusElement(itemElementsIdMap.value.length - 1)
		}
	}

	return {
		initializeNavigation,
		resetNavigation,
	}
}
