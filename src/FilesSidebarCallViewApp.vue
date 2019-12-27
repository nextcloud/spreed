<!--
  - @copyright Copyright (c) 2019, Daniel Calviño Sánchez <danxuliu@gmail.com>
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
	<p v-show="isInCall">
		Call in progress
	</p>
</template>

<script>
import { PARTICIPANT } from './constants'

export default {

	name: 'FilesSidebarCallViewApp',

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

		isInCall() {
			// FIXME Remove participants as soon as the file changes so this
			// condition is not needed.
			if (this.fileId !== this.fileIdForToken) {
				return false
			}

			const participantIndex = this.$store.getters.getParticipantIndex(this.token, this.$store.getters.getParticipantIdentifier())
			if (participantIndex === -1) {
				return false
			}

			const participant = this.$store.getters.getParticipant(this.token, participantIndex)

			return participant.inCall !== PARTICIPANT.CALL_FLAG.DISCONNECTED
		},
	},

	watch: {
		isInCall: function(isInCall) {
			if (isInCall) {
				this.replaceSidebarHeaderContentsWithCallView()
			} else {
				this.restoreSidebarHeaderContents()
			}
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
			for (let i = 0; i < document.styleSheets.length; i++) {
				const sheet = document.styleSheets[i]
				// None of the default properties of a style sheet can be used
				// as an ID. Adding a "data-id" attribute would work in Firefox,
				// but not in Chromium, as it does not provide a "dataset"
				// property in styleSheet objects. Therefore it is necessary to
				// check the rules themselves, but as the order is undefined a
				// matching rule needs to be looked for in all of them.
				if (sheet.cssRules.length !== 1) {
					continue
				}

				for (const cssRule of sheet.cssRules) {
					if (cssRule.cssText === '.app-sidebar-header .hidden-by-call { display: none !important; }') {
						return
					}
				}
			}

			const style = document.createElement('style')

			document.head.appendChild(style)

			// "insertRule" calls below need to be kept in sync with the
			// condition above.

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

				if (!headerChild.classList.contains('app-sidebar__close')) {
					headerChild.classList.add('hidden-by-call')
				}
			}

			header.append(this.$el)
		},

		/**
		 * Shows the sidebar header contents and moves the call view back to the
		 * actions.
		 */
		restoreSidebarHeaderContents() {
			const header = document.querySelector('.app-sidebar-header')
			if (!header) {
				return
			}

			for (let i = 0; i < header.children.length; i++) {
				const headerChild = header.children[i]

				if (!headerChild.classList.contains('app-sidebar__close')) {
					headerChild.classList.remove('hidden-by-call')
				}
			}

			const headerAction = document.querySelector('.app-sidebar-header__action')
			if (headerAction) {
				headerAction.append(this.$el)
			}
		},
	},
}
</script>
