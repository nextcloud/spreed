
/**
 * @copyright Copyright (c) 2019 Marco Ambrosini <marcoambrosini@pm.me>
 *
 * @author Marco Ambrosini <marcoambrosini@pm.me>
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
import { EventBus } from '../services/EventBus'
import Axios from '@nextcloud/axios'
import debounce from 'debounce'
import CancelableRequest from '../utils/cancelableRequest'
import { fetchParticipants } from '../services/participantsService'
import Hex from 'crypto-js/enc-hex'
import SHA1 from 'crypto-js/sha1'
import { PARTICIPANT } from '../constants'
import { emit } from '@nextcloud/event-bus'
import { showError } from '@nextcloud/dialogs'
import isInLobby from './isInLobby'

const getParticipants = {

	mixins: [isInLobby],

	data() {
		return {
			participantsInitialised: false,
			/**
			 * Stores the cancel function for cancelableGetParticipants
			 */
			cancelGetParticipants: () => {},
			fetchingParticipants: false,
		}
	},

	props: {
		isActive: {
			type: Boolean,
			default: true,
		},
	},

	beforeMount() {
		EventBus.$on('route-change', this.onRouteChange)
		EventBus.$on('joined-conversation', this.onJoinedConversation)

		// FIXME this works only temporary until signaling is fixed to be only on the calls
		// Then we have to search for another solution. Maybe the room list which we update
		// periodically gets a hash of all online sessions?
		EventBus.$on('signaling-participant-list-changed', this.debounceUpdateParticipants)
	},

	beforeDestroy() {
		EventBus.$off('route-change', this.onRouteChange)
		EventBus.$off('joined-conversation', this.onJoinedConversation)
		EventBus.$off('signaling-participant-list-changed', this.debounceUpdateParticipants)
	},

	methods: {
		onRouteChange() {
			// Reset participantsInitialised when there is only the current user in the participant list
			this.participantsInitialised = this.$store.getters.participantsList(this.token).length > 1
		},
		/**
		 * If the conversation has been joined, we get the participants
		 */
		onJoinedConversation() {
			this.$nextTick(() => {
				this.cancelableGetParticipants()
			})
		},

		debounceUpdateParticipants() {
			if (!this.$store.getters.windowIsVisible()
				|| !this.$store.getters.getSidebarStatus
				|| !this.isActive) {
				this.debounceSlowUpdateParticipants()
				return
			}

			this.debounceFastUpdateParticipants()
		},

		debounceSlowUpdateParticipants: debounce(function() {
			if (!this.fetchingParticipants) {
				this.cancelableGetParticipants()
			}
		}, 15000),

		debounceFastUpdateParticipants: debounce(function() {
			if (!this.fetchingParticipants) {
				this.cancelableGetParticipants()
			}
		}, 3000),

		async cancelableGetParticipants() {
			if (this.token === '' || this.isInLobby) {
				return
			}

			try {
				// The token must be stored in a local variable to ensure that
				// the same token is used after waiting.
				const token = this.token
				// Clear previous requests if there's one pending
				this.cancelGetParticipants('Cancel get participants')
				// Get a new cancelable request function and cancel function pair
				this.fetchingParticipants = true
				const { request, cancel } = CancelableRequest(fetchParticipants)
				this.cancelGetParticipants = cancel
				const participants = await request(token)
				this.$store.dispatch('purgeParticipantsStore', token)

				const hasUserStatuses = !!participants.headers['x-nextcloud-has-user-statuses']
				participants.data.ocs.data.forEach(participant => {
					this.$store.dispatch('addParticipant', {
						token,
						participant,
					})
					if (participant.participantType === PARTICIPANT.TYPE.GUEST
						|| participant.participantType === PARTICIPANT.TYPE.GUEST_MODERATOR) {
						this.$store.dispatch('forceGuestName', {
							token,
							actorId: Hex.stringify(SHA1(participant.sessionIds[0])),
							actorDisplayName: participant.displayName,
						})
					} else if (participant.actorType === 'users' && hasUserStatuses) {
						emit('user_status:status.updated', {
							status: participant.status,
							message: participant.statusMessage,
							icon: participant.statusIcon,
							clearAt: participant.statusClearAt,
							userId: participant.actorId,
						})
					}
				})
				this.participantsInitialised = true
			} catch (exception) {
				if (!Axios.isCancel(exception)) {
					console.error(exception)
					showError(t('spreed', 'An error occurred while fetching the participants'))
				}
			} finally {
				this.fetchingParticipants = false
			}
		},
	},
}

export default getParticipants
