/*
 * @copyright Copyright (c) 2019 Julius Härtl <jus@bitgrid.net>
 *
 * @author Julius Härtl <jus@bitgrid.net>
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
 *
 */

import NcActionButton from '@nextcloud/vue/dist/Components/NcActionButton.js'
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'

// helpers
/**
 *
 * @param {import('@vue/test-utils').Wrapper} wrapper root wrapper to look for NcActionButton
 * @param {string | Array<string>} text or array of possible texts to look for NcButtons
 * @return {import('@vue/test-utils').Wrapper}
 */
function findNcActionButton(wrapper, text) {
	const actionButtons = wrapper.findAllComponents(NcActionButton)
	const items = (Array.isArray(text))
		? actionButtons.filter(actionButton => text.includes(actionButton.text()))
		: actionButtons.filter(actionButton => actionButton.text() === text)
	if (!items.exists()) {
		return items
	}
	return items.at(0)
}

/**
 *
 * @param {import('@vue/test-utils').Wrapper} wrapper root wrapper to look for NcButton
 * @param {string | Array<string>} text or array of possible texts to look for NcButtons
 * @return {import('@vue/test-utils').Wrapper}
 */
function findNcButton(wrapper, text) {
	const buttons = wrapper.findAllComponents(NcButton)
	const items = (Array.isArray(text))
		? buttons.filter(button => text.includes(button.text()) || text.includes(button.vm.ariaLabel))
		: buttons.filter(button => button.text() === text || button.vm.ariaLabel === text)
	if (!items.exists()) {
		return items
	}
	return items.at(0)
}

export {
	findNcActionButton,
	findNcButton,
}
