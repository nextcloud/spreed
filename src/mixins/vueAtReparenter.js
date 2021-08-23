/**
 *
 * @copyright Copyright (c) 2020, Daniel Calviño Sánchez <danxuliu@gmail.com>
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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

import Vue from 'vue'

/**
 * Mixin to reparent the panel of the vue-at component to a specific element.
 *
 * By default the panel of the vue-at component is a child of the root element
 * of the component. In some cases this may not be desirable (for example, if
 * a parent element uses "overflow: hidden" and causes the panel to be
 * partially hidden), so this mixin reparents the panel to a specific element
 * when it is shown.
 *
 * Components using this mixin require a reference called "at" to the vue-at
 * component. The desired parent element can be specified using the
 * "atWhoPanelParentSelector" property.
 */
export default {

	data() {
		return {
			/**
			 * The selector for the HTML element to reparent the vue-at panel to.
			 */
			atWhoPanelParentSelector: 'body',
			/**
			 * Extra CSS classes to be set in the vue-at panel.
			 */
			atWhoPanelExtraClasses: '',
			at: null,
			atWhoPanelElement: null,
			originalWrapElement: null,
		}
	},

	computed: {
		/**
		 * Returns the "atwho" property of the vue-at component.
		 *
		 * The "atwho" property is an object when the panel is open and null
		 * when the panel is closed.
		 *
		 * @return {object} the "atwho" property of the vue-at component.
		 */
		atwho() {
			if (!this.at) {
				return null
			}

			return this.at.atwho
		},

		/**
		 * Returns a list of CSS clases from the space separated string
		 * "atWhoPanelExtraClasses".
		 *
		 * @return {Array} the list of CSS classes
		 */
		atWhoPanelExtraClassesList() {
			return this.atWhoPanelExtraClasses.split(' ').filter(cssClass => cssClass !== '')
		},
	},

	watch: {
		/**
		 * Reparents the panel of the vue-at component when shown.
		 *
		 * Besides reparenting the panel its position needs to be adjusted to
		 * the new parent. The panel is initially a child of the "wrap" element
		 * of vue-at and vue-at calculates the position of the panel based on
		 * that element. Fortunately the reference to that element is not used
		 * for anything else, so it can be modified while the panel is open to
		 * point to the new parent.
		 *
		 * @param {object} atwho current value of atwho
		 * @param {object} atwhoOld previous value of atwho
		 */
		atwho(atwho, atwhoOld) {
			// Only check whether the object existed or not; its properties are
			// not relevant.
			if ((atwho && atwhoOld) || (!atwho && !atwhoOld)) {
				return
			}

			if (atwho) {
				// Panel will be opened in next tick; defer moving it to the
				// proper parent until that happens
				Vue.nextTick(function() {
					this.atWhoPanelElement = this.at.$refs.wrap.querySelector('.atwho-panel')

					if (this.atWhoPanelExtraClassesList.length > 0) {
						this.atWhoPanelElement.classList.add(...this.atWhoPanelExtraClassesList)
					}

					this.originalWrapElement = this.at.$refs.wrap
					this.at.$refs.wrap = window.document.querySelector(this.atWhoPanelParentSelector)

					const atWhoPanelParentSelector = window.document.querySelector(this.atWhoPanelParentSelector)
					atWhoPanelParentSelector.appendChild(this.atWhoPanelElement)

					// The position of the panel will be automatically adjusted
					// due to the reactivity, but that will happen in next tick.
					// To prevent a flicker due to the change of the panel
					// position the style is explicitly adjusted now.
					const { top, left } = this.at._computedWatchers.style.get()
					this.atWhoPanelElement.style.top = top
					this.atWhoPanelElement.style.left = left
				}.bind(this))
			} else {
				this.at.$refs.wrap = this.originalWrapElement
				this.originalWrapElement = null

				// Panel will be closed in next tick; move it back to the
				// expected parent before that happens.
				this.at.$refs.wrap.appendChild(this.atWhoPanelElement)
			}
		},
	},

	mounted() {
		// $refs is not reactive and its contents are set after the initial
		// render.
		this.at = this.$refs.at
	},

}
