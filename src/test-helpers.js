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

import { cloneDeep } from 'lodash'

import NcActionButton from '@nextcloud/vue/dist/Components/NcActionButton.js'
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcListItem from '@nextcloud/vue/dist/Components/NcListItem.js'

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

/**
 *
 * @param {import('@vue/test-utils').Wrapper} wrapper root wrapper to look for NcListItem
 * @param {string | Array<string>} text or array of possible texts to look for NcListItems
 * @return {import('@vue/test-utils').Wrapper}
 */
function findNcListItems(wrapper, text) {
	const listItems = wrapper.findAllComponents(NcListItem)
	return (Array.isArray(text))
		? listItems.filter(listItem => text.includes(listItem.vm.name))
		: listItems.filter(listItem => listItem.vm.name === text)
}

/**
 *
 * @param {object} data response from the server
 * @param {object} [data.headers = {}] headers of response
 * @param {object} [data.payload = {}] payload of response
 * @param {number} [data.status = 200] status code of response
 * @return {object}
 */
function generateOCSResponse({ headers = {}, payload = {}, status = 200 }) {
	return {
		headers,
		status,
		data: {
			ocs: {
				data: cloneDeep(payload),
				meta: (status >= 200 && status < 400)
					? {
						status: 'ok',
						statuscode: status,
						message: 'OK',
					}
					: {
						status: 'failure',
						statuscode: status,
						message: '',
					},
			},
		},
	}
}

/**
 *
 * @param {object} data response from the server
 * @param {object} [data.headers = {}] headers of response
 * @param {object} [data.payload = {}] payload of response
 * @param {number} data.status status code of response
 * @return {object}
 */
function generateOCSErrorResponse({ headers = {}, payload = {}, status }) {
	return {
		headers,
		status,
		response: {
			status,
			data: {
				ocs: {
					data: cloneDeep(payload),
					meta: {
						status: 'failure',
						statuscode: status,
						message: '',
					},
				},
			},
		},
	}
}

export {
	findNcActionButton,
	findNcButton,
	findNcListItems,
	generateOCSResponse,
	generateOCSErrorResponse,
}
