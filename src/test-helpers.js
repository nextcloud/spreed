/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { cloneDeep } from 'lodash'

// helpers
/**
 *
 * @param {import('@vue/test-utils').Wrapper} wrapper root wrapper to look for NcActionButton
 * @param {string | Array<string>} text or array of possible texts to look for NcButtons
 * @return {import('@vue/test-utils').Wrapper}
 */
function findNcActionButton(wrapper, text) {
	const actionButtons = wrapper.findAllComponents({ name: 'NcActionButton' })
	const items = (Array.isArray(text))
		? actionButtons.filter((actionButton) => text.includes(actionButton.text()))
		: actionButtons.filter((actionButton) => actionButton.text() === text)
	if (!items.exists()) {
		return items
	}
	return items[0]
}

/**
 *
 * @param {import('@vue/test-utils').Wrapper} wrapper root wrapper to look for NcButton
 * @param {string | Array<string>} text or array of possible texts to look for NcButtons
 * @return {import('@vue/test-utils').Wrapper}
 */
function findNcButton(wrapper, text) {
	const buttons = wrapper.findAllComponents({ name: 'NcButton' })
	const items = (Array.isArray(text))
		? buttons.filter((button) => text.includes(button.text()) || text.includes(button.vm.ariaLabel))
		: buttons.filter((button) => button.text() === text || button.vm.ariaLabel === text)
	if (!items.exists()) {
		return items
	}
	return items[0]
}

/**
 *
 * @param {import('@vue/test-utils').Wrapper} wrapper root wrapper to look for NcListItem
 * @param {string | Array<string>} text or array of possible texts to look for NcListItems
 * @return {import('@vue/test-utils').Wrapper}
 */
function findNcListItems(wrapper, text) {
	const listItems = wrapper.findAllComponents({ name: 'NcListItem' })
	return (Array.isArray(text))
		? listItems.filter((listItem) => text.includes(listItem.vm.name))
		: listItems.filter((listItem) => listItem.vm.name === text)
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
	generateOCSErrorResponse,
	generateOCSResponse,
}
