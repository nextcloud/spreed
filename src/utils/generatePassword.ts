/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import axios from '@nextcloud/axios'
// FIXME: should use the capabilities manager to get the password policy
import { localCapabilities } from '../services/CapabilitiesManager.ts'
// note: some chars removed on purpose to make them human friendly when read out
const passwordSet = 'abcdefgijkmnopqrstwxyzABCDEFGHJKLMNPQRSTWXYZ23456789'
/**
 * Generate a valid policy password or request a valid password if password_policy is enabled
 *
 */
export default async function(): Promise<string> {
	// password policy is enabled, let's request a pass
	if (localCapabilities?.password_policy?.api?.generate) {
		try {
			const request = await axios.get(localCapabilities.password_policy.api.generate)
			return request.data.ocs.data.password
		} catch (error) {
			console.info('Error generating password from password_policy', error)
		}
	}
	const array = new Uint8Array(localCapabilities?.password_policy?.minLength ?? 10)
	const ratio = passwordSet.length / 255
	self.crypto.getRandomValues(array)
	let password = ''
	for (let i = 0; i < array.length; i++) {
		password += passwordSet.charAt(array[i] * ratio)
	}
	return password
}
