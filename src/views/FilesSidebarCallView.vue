<!--
  - SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="talk-sidebar-callview">
		<TopBar is-in-call is-sidebar />
		<CallView :token="token" is-sidebar />
	</div>
</template>

<script>
import CallView from '../components/CallView/CallView.vue'
import TopBar from '../components/TopBar/TopBar.vue'
import { useGetToken } from '../composables/useGetToken.ts'
import { useHashCheck } from '../composables/useHashCheck.js'
import { useSessionIssueHandler } from '../composables/useSessionIssueHandler.ts'

export default {
	name: 'FilesSidebarCallView',

	components: {
		CallView,
		TopBar,
	},

	setup() {
		useHashCheck()

		return {
			isLeavingAfterSessionIssue: useSessionIssueHandler(),
			token: useGetToken(),
		}
	},

	created() {
		window.addEventListener('beforeunload', this.preventUnload)
		this.replaceSidebarHeaderContentsWithCallView()
	},

	beforeUnmount() {
		window.removeEventListener('beforeunload', this.preventUnload)
		this.restoreSidebarHeaderContents()
	},

	methods: {
		preventUnload(event) {
			if (this.isLeavingAfterSessionIssue) {
				return
			}

			event.preventDefault()
		},

		/**
		 * Adds a special style sheet to hide the sidebar header contents during
		 * a call.
		 *
		 * The style sheet contains a rule to hide ".hidden-by-call" elements,
		 * which is the CSS class set in the sidebar header contents during a
		 * call.
		 */
		addCallInFilesSidebarStyleSheet() {
			const isCallInFilesSidebarStyleSheet = (sheet) => {
				try {
					// cssRules may not be defined in Chromium if the stylesheet
					// is loaded from a different domain.
					if (!sheet.cssRules) {
						return false
					}

					// None of the default properties of a style sheet can be used
					// as an ID. Adding a "data-id" attribute would work in Firefox,
					// but not in Chromium, as it does not provide a "dataset"
					// property in styleSheet objects. Therefore it is necessary to
					// check the rules themselves, but as the order is undefined a
					// matching rule needs to be looked for in all of them.
					if (sheet.cssRules.length !== 2) {
						return false
					}

					for (const cssRule of sheet.cssRules) {
						if (cssRule.cssText === '.app-sidebar-header .hidden-by-call { display: none !important; }') {
							return true
						}
					}
				} catch (exception) {
					// Accessing cssRules may throw a SecurityError in Firefox
					// if the style sheet is loaded from a different domain.
					if (exception.name !== 'SecurityError') {
						throw exception
					}
				}

				return false
			}

			for (let i = 0; i < document.styleSheets.length; i++) {
				if (isCallInFilesSidebarStyleSheet(document.styleSheets[i])) {
					return
				}
			}

			const style = document.createElement('style')

			document.head.appendChild(style)

			// "insertRule" calls below need to be kept in sync with the
			// condition above.

			// Shadow is added to forced white icons to ensure that they are
			// visible even against a bright video background.
			// White color of forced white icons needs to be set in "icons.scss"
			// file to be able to use the SCSS functions.
			style.sheet.insertRule('.app-sidebar-header .forced-white { filter: drop-shadow(1px 1px 4px var(--color-box-shadow)); }', 0)

			style.sheet.insertRule('.app-sidebar-header .hidden-by-call { display: none !important; }', 0)
		},

		/**
		 * Hides the sidebar header contents (except the close button) and shows
		 * the call view instead.
		 */
		replaceSidebarHeaderContentsWithCallView() {
			this.addCallInFilesSidebarStyleSheet()

			const header = document.querySelector('.app-sidebar-header')
			if (!header) {
				return
			}

			for (let i = 0; i < header.children.length; i++) {
				const headerChild = header.children[i]

				if (headerChild.classList.contains('app-sidebar__close')) {
					headerChild.classList.add('forced-white')
				} else {
					headerChild.classList.add('hidden-by-call')
				}
			}

			header.appendChild(this.$el)
		},

		/**
		 * Shows the sidebar header contents and moves the call view back to the
		 * description.
		 */
		restoreSidebarHeaderContents() {
			const header = document.querySelector('.app-sidebar-header')
			if (!header) {
				return
			}

			for (let i = 0; i < header.children.length; i++) {
				const headerChild = header.children[i]

				if (headerChild.classList.contains('app-sidebar__close')) {
					headerChild.classList.remove('forced-white')
				} else {
					headerChild.classList.remove('hidden-by-call')
				}
			}

			const headerDescription = document.querySelector('.app-sidebar-header__description')
			if (headerDescription) {
				headerDescription.appendChild(this.$el)
			}
		},
	},
}
</script>

<style lang="scss" scoped>
@import '../assets/variables';

#call-container {
	position: relative;

	/* Prevent shadows of videos from leaking on other elements. */
	overflow: hidden;

	/* Show the call container in a 16/9 proportion based on the sidebar
	 * width. */
	padding-bottom: 56.25%;
	max-height: 56.25%;
}

.call-loading{
	padding-bottom: 56.25%;
	max-height: 56.25%;
	background-color: $color-call-background;
}
</style>
