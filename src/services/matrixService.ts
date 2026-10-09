/*!
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type {
	addMatrixHomeserverParams,
	addMatrixHomeserverResponse,
	checkMatrixConnectionResponse,
	getMatrixAccountResponse,
	getMatrixHomeserversResponse,
	linkMatrixAccountParams,
	linkMatrixAccountResponse,
	MatrixHomeserver,
	reloginMatrixAccountParams,
	reloginMatrixAccountResponse,
	removeMatrixHomeserverResponse,
	testMatrixHomeserverResponse,
	unlinkMatrixAccountResponse,
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

/**
 * Get the linked account and the homeservers an account can be linked on
 */
async function getMatrixAccount(): getMatrixAccountResponse {
	return axios.get(generateOcsUrl('apps/spreed/api/v1/matrix/account'))
}

/**
 * Link a Matrix account
 *
 * @param payload The homeserver id, Matrix user and password
 */
async function linkMatrixAccount(payload: linkMatrixAccountParams): linkMatrixAccountResponse {
	return axios.post(generateOcsUrl('apps/spreed/api/v1/matrix/account'), payload)
}

/**
 * Check the connection to the homeserver again
 */
async function checkMatrixConnection(): checkMatrixConnectionResponse {
	return axios.post(generateOcsUrl('apps/spreed/api/v1/matrix/account/check'))
}

/**
 * Log in again after the homeserver rejected the access token
 *
 * @param payload The Matrix password
 */
async function reloginMatrixAccount(payload: reloginMatrixAccountParams): reloginMatrixAccountResponse {
	return axios.put(generateOcsUrl('apps/spreed/api/v1/matrix/account'), payload)
}

/**
 * Unlink the Matrix account
 */
async function unlinkMatrixAccount(): unlinkMatrixAccountResponse {
	return axios.delete(generateOcsUrl('apps/spreed/api/v1/matrix/account'))
}

export {
	addMatrixHomeserver,
	checkMatrixConnection,
	getMatrixAccount,
	getMatrixHomeservers,
	linkMatrixAccount,
	reloginMatrixAccount,
	removeMatrixHomeserver,
	testMatrixHomeserver,
	unlinkMatrixAccount,
	updateMatrixHomeserver,
	updateMatrixSettings,
}
