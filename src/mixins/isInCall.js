/**
 *
 * @copyright Copyright (c) 2020 Joas Schilling <coding@schilljs.com>
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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

import { PARTICIPANT } from '../constants'
import SessionStorage from '../services/SessionStorage'
import { EventBus } from '../services/EventBus'

/**
 * A mixin to check whether the current session of a user is in a call or not.
 *
 * Components using this mixin require a "token" property and a "participant" property with, at least, the "inCall" property.
 */
export default {

	data() {
		return {
			sessionStorageJoinedConversation: null,
		}
	},

	computed: {
		isInCall() {
			return this.sessionStorageJoinedConversation === this.token
				&& this.participant.inCall !== PARTICIPANT.CALL_FLAG.DISCONNECTED
		},
	},

	beforeDestroy() {
		EventBus.$off('joinedConversation', this.readSessionStorageJoinedConversation)
	},

	beforeMount() {
		EventBus.$on('joinedConversation', this.readSessionStorageJoinedConversation)
		this.sessionStorageJoinedConversation = SessionStorage.getItem('joined_conversation')
	},

	methods: {
		readSessionStorageJoinedConversation() {
			this.sessionStorageJoinedConversation = SessionStorage.getItem('joined_conversation')
		},
	},
}
