/**
 * @copyright Copyright (c) 2023 Marcel Müller <marcel.mueller@nextcloud.com>
 *
 * @author Marcel Müller <marcel.mueller@nextcloud.com>
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
