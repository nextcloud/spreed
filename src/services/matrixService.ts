/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import type { AxiosRequestConfig } from '@nextcloud/axios'

import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'

export type MatrixAccount = {
	id: number
	mxid: string
	deviceId: string
	homeserverId: number
	status: number
	lastSync: number | null
	lastError: string | null
}

export type MatrixHomeserverPublic = {
	id: number
	name: string
	serverName: string
}

export type MatrixHomeserver = MatrixHomeserverPublic & {
	baseUrl: string
	enabled: boolean
	allowE2ee: boolean
	allowUpload: boolean
	specVersions: string[]
}

export type MatrixDeviceStatus = {
	deviceId: string
	ed25519: string | null
	curve25519: string | null
	verified: boolean
	crossSigned: boolean
	hasBackupKey: boolean
	backupRestoredAt: number | null
	error?: string
}

export type MatrixVerification = {
	transactionId: string
	state: 'requested' | 'ready' | 'started' | 'accepted' | 'keys_exchanged' | 'mac_sent' | 'done' | 'cancelled'
	theirDeviceId: string | null
	emoji: { emoji: string, name: string }[]
	decimal: [number, number, number] | null
	reason: string | null
}

export type MatrixAccountInfo = {
	enabled: boolean
	canLink: boolean
	account: MatrixAccount | null
	homeservers: MatrixHomeserverPublic[]
	device: MatrixDeviceStatus | null
	verification: MatrixVerification | null
}

export type MatrixOperationalSettings = {
	syncInterval: number
	idleSyncInterval: number
	maxParallelSyncs: number
	foregroundSyncAge: number
	historyEvents: number
	historyDays: number
	maxUpload: number
	typingIn: boolean
	typingOut: boolean
	e2eeSharedLookup: boolean
	e2eeVerifiedOnly: boolean
}

export type MatrixStatus = {
	accounts: { total: number, active: number, error: number, disabled: number, medianSyncAge: number | null }
	rooms: number
	undecryptable: number
	errors: { mxid: string, userId: string, status: number, lastSync: number | null, error: string }[]
}

export const MATRIX_ACCOUNT_STATUS = {
	ACTIVE: 0,
	TOKEN_INVALID: 1,
	DISABLED: 2,
} as const

// --- personal account -------------------------------------------------------

async function getMatrixAccount(options?: AxiosRequestConfig) {
	return axios.get<{ ocs: { data: MatrixAccountInfo } }>(generateOcsUrl('apps/spreed/api/v1/matrix/account'), options)
}

async function linkMatrixAccount(homeserverId: number, user: string, password: string, options?: AxiosRequestConfig) {
	return axios.post<{ ocs: { data: MatrixAccount } }>(generateOcsUrl('apps/spreed/api/v1/matrix/account'), { homeserverId, user, password }, options)
}

async function reloginMatrixAccount(password: string, options?: AxiosRequestConfig) {
	return axios.put<{ ocs: { data: MatrixAccount } }>(generateOcsUrl('apps/spreed/api/v1/matrix/account'), { password }, options)
}

async function unlinkMatrixAccount(options?: AxiosRequestConfig) {
	return axios.delete(generateOcsUrl('apps/spreed/api/v1/matrix/account'), options)
}

async function syncMatrixAccount(options?: AxiosRequestConfig) {
	return axios.post<{ ocs: { data: MatrixAccount } }>(generateOcsUrl('apps/spreed/api/v1/matrix/account/sync'), {}, options)
}

async function startMatrixVerification(options?: AxiosRequestConfig) {
	return axios.post<{ ocs: { data: MatrixVerification } }>(generateOcsUrl('apps/spreed/api/v1/matrix/account/verification'), {}, options)
}

async function getMatrixVerification(options?: AxiosRequestConfig) {
	return axios.get<{ ocs: { data: MatrixVerification | null } }>(generateOcsUrl('apps/spreed/api/v1/matrix/account/verification'), options)
}

async function confirmMatrixVerification(matches: boolean, options?: AxiosRequestConfig) {
	return axios.put<{ ocs: { data: MatrixVerification | null } }>(generateOcsUrl('apps/spreed/api/v1/matrix/account/verification'), { matches }, options)
}

async function cancelMatrixVerification(options?: AxiosRequestConfig) {
	return axios.delete(generateOcsUrl('apps/spreed/api/v1/matrix/account/verification'), options)
}

async function restoreMatrixBackup(recoveryKey: string, options?: AxiosRequestConfig) {
	return axios.post<{ ocs: { data: { imported: number, sessions: number, decrypted: number } } }>(generateOcsUrl('apps/spreed/api/v1/matrix/account/backup'), { recoveryKey }, options)
}

// --- rooms -------------------------------------------------------------------

export type MatrixDirectoryEntry = { roomId: string, name: string, alias: string | null, topic: string | null, members: number, joined: boolean }

async function createMatrixRoom(payload: { name?: string, topic?: string, encrypted?: boolean, public?: boolean, inviteMatrixIds?: string[], inviteUserIds?: string[], direct?: boolean }, options?: AxiosRequestConfig) {
	return axios.post(generateOcsUrl('apps/spreed/api/v1/matrix/room'), payload, options)
}

async function joinMatrixRoom(reference: string, options?: AxiosRequestConfig) {
	return axios.post(generateOcsUrl('apps/spreed/api/v1/matrix/room/join'), { reference }, options)
}

async function getMatrixDirectory(search: string, since: string | null, options?: AxiosRequestConfig) {
	return axios.get<{ ocs: { data: { chunk: MatrixDirectoryEntry[], next_batch: string | null, total: number | null } } }>(generateOcsUrl('apps/spreed/api/v1/matrix/room/directory'), { ...options, params: { search, since } })
}

// --- administration ---------------------------------------------------------

async function getMatrixHomeservers(options?: AxiosRequestConfig) {
	return axios.get<{ ocs: { data: MatrixHomeserver[] } }>(generateOcsUrl('apps/spreed/api/v1/matrix/admin/homeserver'), options)
}

async function addMatrixHomeserver(serverName: string, name: string, baseUrl: string, options?: AxiosRequestConfig) {
	return axios.post<{ ocs: { data: MatrixHomeserver } }>(generateOcsUrl('apps/spreed/api/v1/matrix/admin/homeserver'), { serverName, name, baseUrl }, options)
}

async function updateMatrixHomeserver(id: number, changes: Partial<Pick<MatrixHomeserver, 'name' | 'enabled' | 'allowE2ee' | 'allowUpload' | 'baseUrl'>>, options?: AxiosRequestConfig) {
	return axios.put<{ ocs: { data: MatrixHomeserver } }>(generateOcsUrl('apps/spreed/api/v1/matrix/admin/homeserver/{id}', { id }), changes, options)
}

async function testMatrixHomeserver(id: number, options?: AxiosRequestConfig) {
	return axios.post<{ ocs: { data: MatrixHomeserver } }>(generateOcsUrl('apps/spreed/api/v1/matrix/admin/homeserver/{id}/test', { id }), {}, options)
}

async function removeMatrixHomeserver(id: number, options?: AxiosRequestConfig) {
	return axios.delete(generateOcsUrl('apps/spreed/api/v1/matrix/admin/homeserver/{id}', { id }), options)
}

async function updateMatrixSettings(payload: { enabled?: boolean, allowedGroups?: string[], settings?: Partial<MatrixOperationalSettings> }, options?: AxiosRequestConfig) {
	return axios.put(generateOcsUrl('apps/spreed/api/v1/matrix/admin/settings'), payload, options)
}

async function getMatrixStatus(options?: AxiosRequestConfig) {
	return axios.get<{ ocs: { data: MatrixStatus } }>(generateOcsUrl('apps/spreed/api/v1/matrix/admin/status'), options)
}

export {
	addMatrixHomeserver,
	createMatrixRoom,
	getMatrixDirectory,
	joinMatrixRoom,
	restoreMatrixBackup,
	cancelMatrixVerification,
	confirmMatrixVerification,
	getMatrixVerification,
	startMatrixVerification,
	getMatrixAccount,
	getMatrixHomeservers,
	getMatrixStatus,
	linkMatrixAccount,
	reloginMatrixAccount,
	removeMatrixHomeserver,
	syncMatrixAccount,
	testMatrixHomeserver,
	unlinkMatrixAccount,
	updateMatrixHomeserver,
	updateMatrixSettings,
}
