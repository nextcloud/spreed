
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
import Hex from 'crypto-js/enc-hex.js'
import SHA1 from 'crypto-js/sha1.js'
import debounce from 'debounce'

import Axios from '@nextcloud/axios'
import { showError } from '@nextcloud/dialogs'
import { emit } from '@nextcloud/event-bus'

import { PARTICIPANT } from '../constants.js'
import { EventBus } from '../services/EventBus.js'
import { fetchParticipants } from '../services/participantsService.js'
import { useGuestNameStore } from '../stores/guestNameStore.js'
import CancelableRequest from '../utils/cancelableRequest.js'
import isInLobby from './isInLobby.js'

const getParticipants = {

	mixins: [isInLobby],

	setup() {
		const guestNameStore = useGuestNameStore()
		return { guestNameStore }
	},

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

	methods: {
		initialiseGetParticipantsMixin() {
			EventBus.$on('route-change', this.onRouteChange)
			EventBus.$on('joined-conversation', this.onJoinedConversation)

			// FIXME this works only temporary until signaling is fixed to be only on the calls
			// Then we have to search for another solution. Maybe the room list which we update
			// periodically gets a hash of all online sessions?
			EventBus.$on('signaling-participant-list-changed', this.debounceUpdateParticipants)
		},

		stopGetParticipantsMixin() {
			EventBus.$off('route-change', this.onRouteChange)
			EventBus.$off('joined-conversation', this.onJoinedConversation)
			EventBus.$off('signaling-participant-list-changed', this.debounceUpdateParticipants)
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
				this.cancelableGetParticipants()
			})
		},

		debounceUpdateParticipants() {
			if (!this.isActive) {
				return
			}

			if (this.$store.getters.windowIsVisible()) {
				this.debounceFastUpdateParticipants()
			} else {
				this.debounceSlowUpdateParticipants()
			}

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
			if (this.token === '' || this.isInLobby || !this.isModeratorOrUser) {
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
						this.guestNameStore.forceGuestName({
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
