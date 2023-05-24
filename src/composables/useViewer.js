/*
 * @copyright Copyright (c) 2023 Grigorii Shartsev <me@shgk.me>
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

import { nextTick, watch } from 'vue'

import { useIsInCall } from './useIsInCall.js'
import { useStore } from './useStore.js'

/**
 * @callback OpenViewer
 *
 * @description Open files in the OCA.Viewer taking into account Talk's fullscreen mode and call view
 * @see https://github.com/nextcloud/viewer
 * @param {string} path - The path to the file to be open
 * @param {object} list - The list of the files to be opened
 */

/**
 * Composable with OCA.Viewer helpers
 *
 * @return {{ openViewer: OpenViewer }}
 */
export function useViewer() {
	const store = useStore()
	const isInCall = useIsInCall()

	/**
	 * @type {OpenViewer}
	 */
	const openViewer = (path, list) => {
		if (!OCA.Viewer) {
			return false
		}

		// The Viewer expects a file to be set in the sidebar if the sidebar is open
		if (store.getters.getSidebarStatus) {
			OCA.Files.Sidebar.state.file = path
		}

		if (isInCall.value) {
			store.dispatch('setCallViewMode', { isViewerOverlay: true })
		}

		OCA.Viewer.open({
			path,
			list,
			onClose: () => {
				store.dispatch('setCallViewMode', { isViewerOverlay: false })
			},
		})

		// FIXME Remove this hack once it is possible to set the parent
		// element of the viewer.
		// By default the viewer is a sibling of the fullscreen element, so
		// it is not visible when in fullscreen mode. It is not possible to
		// specify the parent nor to know when the viewer was actually
		// opened, so for the time being it is reparented if needed shortly
		// after calling it.
		// @see https://github.com/nextcloud/viewer/issues/995
		watch(
			() => store.getters.isFullscreen(),
			async (newIsFullscreen, oldIsFullscreen) => {
				// Wait viewer to be mounted
				await nextTick()

				const viewerElement = document.getElementById('viewer')
				const talkVueApp = document.getElementById('content-vue')

				if (newIsFullscreen) {
					// When changed to the fullscreen mode, Viewer should be moved to the talk app
					talkVueApp.appendChild(viewerElement)
				} else if (oldIsFullscreen) {
					// In normal mode if it was in fullscreen before, move back to body
					// Otherwise it will be overlapped by web-page's header
					document.body.appendChild(viewerElement)
				}
			},
			{ immediate: true },
		)
	}

	return {
		openViewer,
	}
}
