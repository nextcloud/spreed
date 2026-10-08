/*!
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type {
	addMatrixHomeserverParams,
	addMatrixHomeserverResponse,
	getMatrixHomeserversResponse,
	MatrixHomeserver,
	removeMatrixHomeserverResponse,
	testMatrixHomeserverResponse,
	updateMatrixHomeserverParams,
	updateMatrixHomeserverResponse,
	updateMatrixSettingsParams,
	updateMatrixSettingsResponse,
} from '../types/index.ts'

import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'

/**
 * List the configured homeservers
 */
async function getMatrixHomeservers(): getMatrixHomeserversResponse {
	return axios.get(generateOcsUrl('apps/spreed/api/v1/matrix/admin/homeserver'))
}

/**
 * Add a homeserver
 *
 * @param payload The server name, optional label and optional client API base URL
 */
async function addMatrixHomeserver(payload: addMatrixHomeserverParams): addMatrixHomeserverResponse {
	return axios.post(generateOcsUrl('apps/spreed/api/v1/matrix/admin/homeserver'), payload)
}

/**
 * Update a homeserver
 *
 * @param id The homeserver id
 * @param changes The properties to change
 */
async function updateMatrixHomeserver(id: MatrixHomeserver['id'], changes: updateMatrixHomeserverParams): updateMatrixHomeserverResponse {
	return axios.put(generateOcsUrl('apps/spreed/api/v1/matrix/admin/homeserver/{id}', { id }), changes)
}

/**
 * Test the connection to a homeserver
 *
 * @param id The homeserver id
 */
async function testMatrixHomeserver(id: MatrixHomeserver['id']): testMatrixHomeserverResponse {
	return axios.post(generateOcsUrl('apps/spreed/api/v1/matrix/admin/homeserver/{id}/test', { id }))
}

/**
 * Remove a homeserver
 *
 * @param id The homeserver id
 */
async function removeMatrixHomeserver(id: MatrixHomeserver['id']): removeMatrixHomeserverResponse {
	return axios.delete(generateOcsUrl('apps/spreed/api/v1/matrix/admin/homeserver/{id}', { id }))
}

/**
 * Update the feature toggle and group restriction
 *
 * @param payload The settings to change
 */
async function updateMatrixSettings(payload: updateMatrixSettingsParams): updateMatrixSettingsResponse {
	return axios.put(generateOcsUrl('apps/spreed/api/v1/matrix/admin/settings'), payload)
}

export {
	addMatrixHomeserver,
	getMatrixHomeservers,
	removeMatrixHomeserver,
	testMatrixHomeserver,
	updateMatrixHomeserver,
	updateMatrixSettings,
}
