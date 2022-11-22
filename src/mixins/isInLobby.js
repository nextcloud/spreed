/**
 *
 * @copyright Copyright (c) 2020, Daniel Calviño Sánchez <danxuliu@gmail.com>
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

import { PARTICIPANT, WEBINAR } from '../constants.js'

/**
 * Mixin to check whether the current participant is waiting in the lobby or
 * not.
 *
 * Components using this mixin require a "conversation" property (that can be
 * null) with, at least, "participantType" and "lobbyState" properties.
 */
export default {

	computed: {
		isModerator() {
			return this.conversation
				&& (this.conversation.participantType === PARTICIPANT.TYPE.OWNER
					|| this.conversation.participantType === PARTICIPANT.TYPE.MODERATOR
					|| this.conversation.participantType === PARTICIPANT.TYPE.GUEST_MODERATOR)
		},

		isInLobby() {
			return this.conversation
				&& this.conversation.lobbyState === WEBINAR.LOBBY.NON_MODERATORS
				&& !this.isModerator
				&& (this.conversation.permissions & PARTICIPANT.PERMISSIONS.LOBBY_IGNORE) === 0
		},
	},

}
