import type { certificateExpirationParams, certificateExpirationResponse } from '../types/index.ts'

/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'

/**
 * Retrieves the certificate expiration of the specified host
 *
 * @param host The host to check the certificate
 */
const getCertificateExpiration = async (host: certificateExpirationParams['host']): certificateExpirationResponse => {
	return axios.get(generateOcsUrl('apps/spreed/api/v1/certificate/expiration'), {
		params: {
			host,
		} as certificateExpirationParams,
	})
}

/**
 * Checks if the certificate of a host is valid
 *
 * @param host The host to check the certificate
 * @return {boolean} true if the certificate is valid, false otherwise
 */
const isCertificateValid = async (host: certificateExpirationParams['host']): Promise<boolean> => {
	try {
		const response = await getCertificateExpiration(host)

		// Null if unable to retrieve the certificates expiration, otherwise the expiration in days (negative if already expired)
		const expiration = response.data.ocs.data.expiration_in_days

		if (expiration === null) {
			console.warn('Unable to check certificate of', host)
			return false
		} else if (expiration < 0) {
			console.error('Certificate of', host, 'expired')
		} else {
			console.info('Certificate of', host, 'is valid for', expiration, 'days')
		}
		return expiration > 0
	} catch (error) {
		console.error(error)
		return false
	}
}

export {
	getCertificateExpiration,
	isCertificateValid,
}
