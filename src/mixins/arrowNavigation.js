/**
 * @copyright Copyright (c) 2020 John Molakvoæ <skjnldsv@protonmail.com>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

const arrowNavigation = {

	data() {
		return {
			focused: null,
		}
	},

	methods: {
		/**
		 * Functions to implement by the user of this mixin
		getFocusableList() {
			return this.$el.querySelectorAll('li.acli_wrapper .acli')
		},
		focusCancel() {
			return this.abortSearch()
		},
		isFocused() {
			return this.isSearching
		},
		 */

		mountArrowNavigation() {
			document.addEventListener('keydown', (event) => {
				// https://www.w3.org/WAI/GL/wiki/Using_ARIA_menus
				if (this.isFocused()) {
					// If arrow down, focus next result
					if (event.key === 'ArrowDown') {
						this.focusNext(event)
					}

					// If arrow up, focus prev result
					if (event.key === 'ArrowUp') {
						this.focusPrev(event)
					}

					// Reset search
					if (event.key === 'Escape') {
						this.focusCancel()
					}
				}
			})
		},

		/**
		 * If we have items already, open first one
		 */
		onInputEnter() {
			const items = this.getFocusableList()
			if (items.length) {
				items[0].click()
			}
		},

		/**
		 * If none already focused, focus the first rendered result
		 * @param {Event} event the keydown event
		 */
		focusInitialise(event) {
			if (this.focused === null) {
				this.focusFirst()
			}
		},

		/**
		 * Focus the first item if any
		 * @param {Event} event the keydown event
		 */
		focusFirst(event) {
			const items = this.getFocusableList()
			if (items && items.length > 0) {
				if (event) {
					event.preventDefault()
				}
				this.focused = 0
				this.focusIndex(this.focused)
			}
		},

		/**
		 * Focus the next item if any
		 * @param {Event} event the keydown event
		 */
		focusNext(event) {
			if (this.focused === null) {
				this.focusFirst(event)
				return
			}

			const items = this.getFocusableList()

			// If we're not focusing the last, focus the next one
			if (items && items.length > 0) {
				event.preventDefault()
				if (this.focused + 1 >= items.length) {
					// When we are out of scope, reset the focus to the last item
					this.focused = items.length - 1
				} else {
					this.focused++
				}
				this.focusIndex(this.focused)
			}
		},

		/**
		 * Focus the previous item if any
		 * @param {Event} event the keydown event
		 */
		focusPrev(event) {
			if (this.focused === null) {
				this.focusFirst(event)
				return
			}

			const items = this.getFocusableList()
			// If we're not focusing the first, focus the previous one
			if (items && items.length > 0 && this.focused > 0) {
				event.preventDefault()
				if (this.focused > items.length) {
					// When we are out of scope, reset the focus to the last item
					this.focused = items.length - 1
				} else {
					this.focused--
				}
				this.focusIndex(this.focused)
			}

		},

		/**
		 * Focus the specified item index if it exists
		 * @param {number} index the item index
		 */
		focusIndex(index) {
			const items = this.getFocusableList()
			if (items && items[index]) {
				items[index].focus()
			}
		},

		/**
		 * Set the current focused element based on the target
		 * @param {Event} event the focus event
		 */
		setFocusedIndex(event) {
			const entry = event.target
			const items = this.getFocusableList()
			const index = [...items].findIndex(search => search === entry)
			if (index > -1) {
				// let's not use focusIndex as the entry is already focused
				this.focused = index
			}
		},
	},
}

export default arrowNavigation
