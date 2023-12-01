/**
 * @copyright Copyright (c) 2019 Marco Ambrosini <marcoambrosini@icloud.com>
 *
 * @author Marco Ambrosini <marcoambrosini@icloud.com>
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
import debounce from 'debounce'

import { subscribe, unsubscribe } from '@nextcloud/event-bus'

import { EventBus } from '../services/EventBus.js'
import isInLobby from './isInLobby.js'

const getParticipants = {

	mixins: [isInLobby],

	data() {
		return {
			participantsInitialised: false,
			fetchingParticipants: false,
			pendingChanges: false,
			debounceFastUpdateParticipants: null,
			debounceSlowUpdateParticipants: null,
		}
	},

	props: {
		isActive: {
			type: Boolean,
			default: true,
		},
	},

	created() {
		this.debounceFastUpdateParticipants = debounce(function() {
			this.cancelableGetParticipants()
		}, 3000)

		this.debounceSlowUpdateParticipants = debounce(function() {
			this.cancelableGetParticipants()
		}, 15000)
	},

	methods: {
		initialiseGetParticipantsMixin() {
			EventBus.$on('route-change', this.onRouteChange)
			EventBus.$on('joined-conversation', this.onJoinedConversation)

			// FIXME this works only temporary until signaling is fixed to be only on the calls
			// Then we have to search for another solution. Maybe the room list which we update
			// periodically gets a hash of all online sessions?
			EventBus.$on('signaling-participant-list-changed', this.debounceUpdateParticipants)

			subscribe('guest-promoted', this.onJoinedConversation)
		},

		stopGetParticipantsMixin() {
			EventBus.$off('route-change', this.onRouteChange)
			EventBus.$off('joined-conversation', this.onJoinedConversation)
			EventBus.$off('signaling-participant-list-changed', this.debounceUpdateParticipants)

			unsubscribe('guest-promoted', this.onJoinedConversation)
		},

		onRouteChange() {
			// Reset participantsInitialised when there is only the current user in the participant list
			this.participantsInitialised = this.$store.getters.participantsList(this.token).length > 1
		},
		/**
		 * If the conversation has been joined, we get the participants
		 */
		onJoinedConversation() {
			this.$nextTick(() => {
				this.debounceUpdateParticipants()
			})
		},

		debounceUpdateParticipants() {
			if (!this.isActive && !this.isInCall) {
				// Update is ignored but there is a flag to force the participants update
				this.pendingChanges = true
				return
			}

			// this.conversation is provided by component, where mixin is used
			if (this.$store.getters.windowIsVisible()
				&& (this.isInCall || !this.conversation?.hasCall)) {
				this.debounceFastUpdateParticipants()
			} else {
				this.debounceSlowUpdateParticipants()
			}
			this.pendingChanges = false
		},

		async cancelableGetParticipants() {
			if (this.fetchingParticipants || this.token === '' || this.isInLobby || !this.isModeratorOrUser) {
				return
			}

			this.fetchingParticipants = true

			// Clear previously requested updates
			this.debounceFastUpdateParticipants.clear()
			this.debounceSlowUpdateParticipants.clear()

			const response = await this.$store.dispatch('fetchParticipants', { token: this.token })
			if (response) {
				this.participantsInitialised = true
			}
			this.fetchingParticipants = false
		},
	},
}

export default getParticipants
