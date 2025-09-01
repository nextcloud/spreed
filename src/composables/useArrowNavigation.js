/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { computed, onMounted, ref, unref } from 'vue'

/* selector to check, if item element or its children are focusable */
const focusableCondition = 'a, button, input, select, textarea, [tabindex]:not([tabindex="-1"])'

/**
 * Mount navigation according to https://www.w3.org/WAI/GL/wiki/Using_ARIA_menus
 * Item elements should have unique "data-nav-id" attribute
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
 * @param {object} options navigation options
 * @param {boolean} [options.confirmEnter] flag to confirm Enter click
 */
export function useArrowNavigation(listElementRef, defaultElementRef, options = { confirmEnter: false }) {
	const listRef = ref(null)
	const defaultRef = ref(null)

	/**
	 * @constant
	 * @type {import('vue').Ref<HTMLElement[]>}
	 */
	const itemElements = ref([])
	const itemElementsIdMap = computed(() => itemElements.value.map((item) => {
		return item.getAttribute('data-nav-id')
	}))

	const focusedIndex = ref(null)
	const isConfirmationEnabled = ref(null)

	const lookupNavId = (element) => {
		if (element.hasAttribute('data-nav-id')) {
			return element.getAttribute('data-nav-id')
		}
		// Find parent element with data-nav-id attribute
		let parentElement = element.parentNode
		while (parentElement && parentElement !== document.body) {
			if (parentElement.hasAttribute('data-nav-id')) {
				return parentElement.getAttribute('data-nav-id')
			}
			parentElement = parentElement.parentNode
		}
	}

	// Set focused index according to selected element
	const handleFocusEvent = (event) => {
		const newIndex = itemElementsIdMap.value.indexOf(lookupNavId(event.target))
		// Quit if triggered by arrow navigation as already handled
		// or if using Tab key to navigate, and going through NcActions
		if (focusedIndex.value !== newIndex && newIndex !== -1) {
			focusedIndex.value = newIndex
		}
	}

	// Reset focused index if focus moved out of navigation area or moved to the defaultRef
	const handleBlurEvent = (event) => {
		if (!listRef.value?.contains(event.relatedTarget)
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

		listRef.value?.addEventListener('keydown', (event) => {
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
		itemElements.value = Array.from(listRef.value?.querySelectorAll('[data-nav-id]'))
		focusedIndex.value = null

		listRef.value?.addEventListener('focus', handleFocusEvent, true)
		listRef.value?.addEventListener('blur', handleBlurEvent, true)
	}

	/**
	 * Remove listeners from navigation area, reset list of elements
	 * (to made navigation unavailable during fetching results)
	 */
	function resetNavigation() {
		itemElements.value = []

		listRef.value?.removeEventListener('focus', handleFocusEvent, true)
		listRef.value?.removeEventListener('blur', handleBlurEvent, true)
	}

	/**
	 * Focus natively the DOM element specified by index
	 *
	 * @param {number} index the item index
	 */
	function nativelyFocusElement(index) {
		focusedIndex.value = index
		const itemElement = itemElements.value[index]

		if (itemElement.matches(focusableCondition)) {
			itemElement.focus()
			return
		}

		try {
			itemElement.querySelector(focusableCondition).focus()
		} catch (e) {
			console.warn('Nav element does not have any focusable children')
		}
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
				&& listRef.value?.contains(itemElements.value[0])) {
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
