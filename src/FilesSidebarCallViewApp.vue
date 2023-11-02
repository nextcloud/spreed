<!--
  - @copyright Copyright (c) 2019, Daniel Calviño Sánchez <danxuliu@gmail.com>
  -
  - @author Marco Ambrosini <marcoambrosini@icloud.com>
  -
  - @license GNU AGPL version 3 or any later version
  -
  - This program is free software: you can redistribute it and/or modify
  - it under the terms of the GNU Affero General Public License as
  - published by the Free Software Foundation, either version 3 of the
  - License, or (at your option) any later version.
  -
  - This program is distributed in the hope that it will be useful,
  - but WITHOUT ANY WARRANTY; without even the implied warranty of
  - MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  - GNU Affero General Public License for more details.
  -
  - You should have received a copy of the GNU Affero General Public License
  - along with this program. If not, see <http://www.gnu.org/licenses/>.
  -
  -->

<template>
	<div v-if="isInFile" class="talk-sidebar-callview">
		<TopBar v-if="showCallView"
			:is-in-call="true"
			:is-sidebar="true" />
		<CallView v-if="showCallView"
			:token="token"
			:is-sidebar="true" />
		<PreventUnload :when="warnLeaving" />
	</div>
</template>

<script>
import PreventUnload from 'vue-prevent-unload'

import LoadingComponent from './components/LoadingComponent.vue'

import { useIsInCall } from './composables/useIsInCall.js'
import participant from './mixins/participant.js'
import sessionIssueHandler from './mixins/sessionIssueHandler.js'
import talkHashCheck from './mixins/talkHashCheck.js'

export default {

	name: 'FilesSidebarCallViewApp',

	components: {
		CallView: () => ({
			component: import(/* webpackChunkName: "files-sidebar-call-chunk" */'./components/CallView/CallView.vue'),
			loading: {
				render: (h) => h(LoadingComponent, { class: 'call-loading' }),
			},
		}),
		PreventUnload,
		TopBar: () => import(/* webpackChunkName: "files-sidebar-call-chunk" */'./components/TopBar/TopBar.vue'),
	},

	mixins: [
		sessionIssueHandler,
		participant,
		talkHashCheck,
	],

	setup() {
		return { isInCall: useIsInCall() }
	},

	data() {
		return {
			// Needed for reactivity.
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

		token() {
			return this.$store.getters.getToken()
		},

		fileIdForToken() {
			return this.$store.getters.getFileIdForToken()
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

	methods: {
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
@import './assets/variables';

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
