/**
 * @copyright Copyright (c) 2020 Marco Ambrosini <marcoambrosini@pm.me>
 *
 * @author Marco Ambrosini <marcoambrosini@pm.me>
 *
 * @license GNU AGPL version 3 or any later version
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

import store from '../store/index'
import SHA1 from 'crypto-js/sha1'
import Hex from 'crypto-js/enc-hex'

const createTemporaryMessage = (text, token, uploadId, index, file, localUrl) => {
	const messageToBeReplied = store.getters.getMessageToBeReplied(token)
	const date = new Date()
	let tempId = 'temp-' + date.getTime()
	const messageParameters = {}
	if (file) {
		tempId += '-' + uploadId + '-' + Math.random()
		messageParameters.file = {
			'type': 'file',
			'file': file,
			'mimetype': file.type,
			'id': tempId,
			'name': file.name,
			// index, will be the id from now on
			uploadId,
			localUrl,
			index,
		}
	}
	const message = Object.assign({}, {
		id: tempId,
		actorId: store.getters.getActorId(),
		actorType: store.getters.getActorType(),
		actorDisplayName: store.getters.getDisplayName(),
		timestamp: 0,
		systemMessage: '',
		messageType: '',
		message: text,
		messageParameters,
		token: token,
		isReplyable: false,
		referenceId: Hex.stringify(SHA1(tempId)),
	})

	if (store.getters.getActorType() === 'guests') {
		// Strip off "guests/" from the sessionHash
		message.actorId = store.getters.getActorId().substring(6)
	}

	/**
	 * If the current message is a quote-reply message, add the parent key to the
	 * temporary message object.
	 */
	if (messageToBeReplied) {
		message.parent = messageToBeReplied.id
	}
	return message
}

export default createTemporaryMessage
