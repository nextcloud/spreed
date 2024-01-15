/**
 * @copyright Copyright (c) 2024 Maksim Sukharev <antreesy.web@gmail.com>
 *
 * @author Maksim Sukharev <antreesy.web@gmail.com>
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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

/**
 * Provide access credentials and params to access a remote server
 * @param {object} conversation conversation object
 * @param {string} userId current user id
 * @return {object}
 */
export function useFederationAccess(conversation, userId) {
	// FIXME useStore() is not accessible outside of components in Vue2
	// Fix after using conversation and userId from the Pinia stores
	const { remoteServer, remoteToken, remoteAccessToken } = conversation || {}
	if (!remoteServer || !remoteToken || !remoteAccessToken) {
		return {}
	}
	const authString = userId + '@' + window.location.host + ':' + remoteAccessToken

	return {
		remoteServer,
		remoteToken,
		headers: {
			Authorization: 'Basic ' + window.btoa(authString),
			'X-Nextcloud-Federation': '1',
			// FIXME check headers and keep only needed (* is for CORS)
			'Access-Control-Allow-Origin': '*',
			'Access-Control-Allow-Methods': 'HEAD, GET, POST, PUT, PATCH, DELETE',
			'Access-Control-Allow-Headers': 'Origin, Content-Type, X-Auth-Token',
		}
	}
}
