/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { createWrapperError } from '@vue/test-utils'
import { cloneDeep } from 'lodash'

import NcActionButton from '@nextcloud/vue/dist/Components/NcActionButton.js'
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcListItem from '@nextcloud/vue/dist/Components/NcListItem.js'

// helpers
/**
 *
 * @param {import('@vue/test-utils').Wrapper} wrapper root wrapper to look for NcActionButton
 * @param {string | Array<string>} text or array of possible texts to look for NcButtons
 * @return {import('@vue/test-utils').VueWrapper | import('@vue/test-utils').ErrorWrapper}
 */
function findNcActionButton(wrapper, text) {
	const actionButtons = wrapper.findAllComponents(NcActionButton)
	const items = actionButtons.filter(actionButton => actionButton.text()
		&& text.includes(actionButton.text()))
	if (!items.length) {
		return createWrapperError('VueWrapper')
	}
	return items.at(0)
}

/**
 *
 * @param {import('@vue/test-utils').Wrapper} wrapper root wrapper to look for NcButton
 * @param {string | Array<string>} text or array of possible texts to look for NcButtons
 * @return {import('@vue/test-utils').VueWrapper | import('@vue/test-utils').ErrorWrapper}
 */
function findNcButton(wrapper, text) {
	const buttons = wrapper.findAllComponents(NcButton)
	const items = buttons.filter(button => (button.text() && text.includes(button.text()))
		|| (button.props('ariaLabel') && text.includes(button.props('ariaLabel'))))
	if (!items.length) {
		return createWrapperError('VueWrapper')
	}
	return items.at(0)
}

/**
 *
 * @param {import('@vue/test-utils').Wrapper} wrapper root wrapper to look for NcListItem
 * @param {string | Array<string>} text or array of possible texts to look for NcListItems
 * @return {Array<import('@vue/test-utils').VueWrapper> | import('@vue/test-utils').ErrorWrapper}
 */
function findNcListItems(wrapper, text) {
	const listItems = wrapper.findAllComponents(NcListItem)
		.filter(listItem => listItem.props('name') && text.includes(listItem.props('name')))

	if (!listItems.length) {
		return createWrapperError('VueWrapper')
	}
	return listItems
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
