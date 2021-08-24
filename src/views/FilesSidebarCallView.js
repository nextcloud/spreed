/**
 *
 * @copyright Copyright (c) 2019, Daniel Calviño Sánchez <danxuliu@gmail.com>
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
		this.callViewInstance = OCA.Talk.newCallView()

		this.$el = document.createElement('div')
		this.id = 'FilesSidebarCallView'

		this.callViewInstance.$mount(this.$el)
		this.$el = this.callViewInstance.$el

		this.$el.replaceAll = function(target) {
			target.replaceWith(this.$el)
		}.bind(this)
	}

	setFileInfo(fileInfo) {
		// The FilesSidebarCallViewApp is the first (and only) child of the Vue
		// instance.
		this.callViewInstance.$children[0].setFileInfo(fileInfo)
	}

}
