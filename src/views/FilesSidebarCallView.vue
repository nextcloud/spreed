<!--
  - SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div v-if="isInFile" class="talk-sidebar-callview">
		<TopBar
			v-if="showCallView"
			:is-in-call="true"
			:is-sidebar="true" />
		<CallView
			v-if="showCallView"
			:token="token"
			:is-sidebar="true" />
	</div>
</template>

<script>
import CallView from '../components/CallView/CallView.vue'
import TopBar from '../components/TopBar/TopBar.vue'
import { useGetToken } from '../composables/useGetToken.ts'
import { useHashCheck } from '../composables/useHashCheck.js'
import { useIsInCall } from '../composables/useIsInCall.js'
import { useSessionIssueHandler } from '../composables/useSessionIssueHandler.ts'
import { useTokenStore } from '../stores/token.ts'

export default {
	name: 'FilesSidebarCallView',

	components: {
		CallView,
		TopBar,
	},

	setup() {
		useHashCheck()

		return {
			isInCall: useIsInCall(),
			isLeavingAfterSessionIssue: useSessionIssueHandler(),
			token: useGetToken(),
			tokenStore: useTokenStore(),
		}
	},

	data() {
		return {
			Talk: OCA.Talk,
		}
	},

	computed: {
		fileInfo() {
			// When changing files OCA.Talk.fileInfo is cleared as soon as the
			// new file starts to be loaded; "setFileInfo()" is called once the
			// new file has loaded, so fileInfo is got from OCA.Talk to hide the
			// call view at the same time as the rest of the sidebar UI.
			return this.Talk.fileInfo || {}
		},

		fileId() {
			return this.fileInfo.id
		},

		fileIdForToken() {
			return this.tokenStore.fileIdForToken
		},

		/**
		 * Returns whether the sidebar is opened in the file of the current
		 * conversation or not.
		 *
		 * Note that false is returned too when the sidebar is closed, even if
		 * the conversation is active in the current file.
		 *
		 * @return {boolean} true if the sidebar is opened in the file, false
		 *          otherwise.
		 */
		isInFile() {
			return this.fileId === this.fileIdForToken
		},

		showCallView() {
			// FIXME Remove participants as soon as the file changes so this
			// condition is not needed.
			if (!this.isInFile) {
				return false
			}

			return this.isInCall
		},

		warnLeaving() {
			return !this.isLeavingAfterSessionIssue && this.showCallView
		},
	},

	watch: {
		showCallView(showCallView) {
			if (showCallView) {
				this.replaceSidebarHeaderContentsWithCallView()
			} else {
				this.restoreSidebarHeaderContents()
			}
		},

		/**
		 * Force restoring the sidebar header contents on file changes.
		 *
		 * If the sidebar is opened in a different file during a call the
		 * sidebar header contents may not be properly restored due to the order
		 * in which the updates are handled, so it needs to be executed again
		 * when the FileInfo has been set and it does not match the current
		 * conversation.
		 *
		 * @param {object} fileInfo the watched FileInfo
		 */
		fileInfo(fileInfo) {
			if (!fileInfo) {
				return
			}

			if (this.isInFile) {
				return
			}

			const headerDescription = document.querySelector('.app-sidebar-header__description')
			if (!headerDescription) {
				return
			}

			if (this.$el.parentElement === headerDescription) {
				return
			}

			this.restoreSidebarHeaderContents()
		},
	},

	created() {
		window.addEventListener('beforeunload', this.preventUnload)
	},

	beforeUnmount() {
		window.removeEventListener('beforeunload', this.preventUnload)
	},

	methods: {
		preventUnload(event) {
			if (!this.warnLeaving) {
				return
			}

			event.preventDefault()
		},

		setFileInfo(fileInfo) {
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
