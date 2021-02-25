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

import SessionStorage from '../services/SessionStorage'
import { EventBus } from '../services/EventBus'

/**
 * A mixin to check whether the user joined the call of the current token in this PHP session or not.
 */
export default {

	data() {
		return {
			sessionStorageJoinedConversation: null,
		}
	},

	computed: {
		isInCall() {
			return this.sessionStorageJoinedConversation === this.$store.getters.getToken()
				&& this.$store.getters.isInCall(this.$store.getters.getToken())
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
