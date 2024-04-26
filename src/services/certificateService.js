/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import axios from '@nextcloud/axios'
import { generateOcsUrl } from '@nextcloud/router'

/**
 * Retrieves the certificate expiration of the specified host
 *
 * @param {string} host The host to check the certificate
 * @return {number|null} Null if unable to retrieve the certificates expiration, otherwise the expiration in days (negative if already expired)
 */
const getCertificateExpiration = async (host) => {
	try {
		const response = await axios.get(generateOcsUrl('apps/spreed/api/v1/certificate/expiration'), {
			params: {
				host,
			},
		})

		return response.data.ocs.data.expiration_in_days
	} catch (error) {
		console.error(error)
	}

	return null
}

/**
 * Checks if the certificate of a host is valid
 *
 * @param {string} host The host to check the certificate
 * @return {boolean} true if the certificate is valid, false otherwise
 */
const isCertificateValid = async (host) => {
	const expiration = await getCertificateExpiration(host)

	if (expiration == null) {
		console.warn('Unable to check certificate of', host)
	} else if (expiration < 0) {
		console.error('Certificate of', host, 'expired')
	} else {
		console.info('Certificate of', host, 'is valid for', expiration, 'days')
	}

	return expiration > 0
}

export {
	getCertificateExpiration,
	isCertificateValid,
}
