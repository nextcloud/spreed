/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/**
 * Helper class to wrap a Vue instance with a FilesSidebarCallViewApp component
 * to be used as a secondary view in the Files sidebar.
 *
 * Although Vue instances/components can be added as tabs to the Files sidebar
 * currently only legacy views can be added as secondary views to the Files
 * sidebar. Those legacy views are expected to provide a root element, $el, with
 * a "replaceAll" method that replaces the given element with the $el element,
 * and a "setFileInfo" method that is called when the sidebar is opened or the
 * current file changes.
 */
export default class FilesSidebarCallView {
	constructor() {
		const callViewApp = OCA.Talk.newCallView()

		const container = document.createElement('div')
		container.id = 'FilesSidebarCallView'

		this.callViewInstance = callViewApp.mount(container)
		this.$el = this.callViewInstance.$el

		this.$el.replaceAll = function(target) {
			target.replaceWith(this.$el)
		}.bind(this)
	}

	setFileInfo(fileInfo) {
		// The FilesSidebarCallViewApp is the first (and only) child of the Vue
		// instance.
		this.callViewInstance.setFileInfo(fileInfo)
	}
}
