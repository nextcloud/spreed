<!--
  - @copyright Copyright (c) 2019 Joas Schilling <coding@schilljs.com>
  -
  - @author Joas Schilling <coding@schilljs.com>
  -
  - @license GNU AGPL version 3 or any later version
  -
  - This program is free software: you can redistribute it and/or modify
  - it under the terms of the GNU Affero General Public License as
  - published by the Free Software Foundation, either version 3 of the
  - License, or (at your option) any later version.
  -
  - This program is distributed in the hope that it will be useful,
  - but WITHOUT ANY WARRANTY; without even the implied warranty of
  - MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
  - GNU Affero General Public License for more details.
  -
  - You should have received a copy of the GNU Affero General Public License
  - along with this program. If not, see <http://www.gnu.org/licenses/>.
-->

<template>
	<li
		class="participant-row"
		:class="{
			'offline': isOffline,
			'currentUser': isSelf,
			'guestUser': isGuest,
			'selected': isSelected }"
		@click="handleClick">
		<AvatarWrapper
			:id="computedId"
			:disable-tooltip="true"
			:size="44"
			:show-user-status="showUserStatus && !isSearched"
			:show-user-status-compact="false"
			:name="computedName"
			:source="participant.source"
			:offline="isOffline" />
		<div class="participant-row__user-wrapper">
			<div class="participant-row__user-descriptor">
				<span class="participant-row__user-name">{{ computedName }}</span>
				<span v-if="showModeratorLabel" class="participant-row__moderator-indicator">({{ t('spreed', 'moderator') }})</span>
				<span v-if="isGuest" class="participant-row__guest-indicator">({{ t('spreed', 'guest') }})</span>
			</div>
			<div v-if="getStatusMessage(participant)"
				class="participant-row__status">
				<span>{{ getStatusMessage(participant) }}</span>
			</div>
		</div>
		<div v-if="callIcon"
			class="participant-row__callstate-icon">
			<Microphone
				v-if="callIcon === 'audio'"
				:size="24"
				decorative />
			<Phone
				v-if="callIcon === 'phone'"
				:size="24"
				decorative />
			<Video
				v-if="callIcon === 'video'"
				:size="24"
				decorative />
		</div>
		<Actions
			v-if="canModerate && !isSearched"
			:aria-label="t('spreed', 'Participant settings')"
			class="participant-row__actions">
			<ActionButton v-if="canBeDemoted"
				icon="icon-rename"
				@click="demoteFromModerator">
				{{ t('spreed', 'Demote from moderator') }}
			</ActionButton>
			<ActionButton v-if="canBePromoted"
				icon="icon-rename"
				@click="promoteToModerator">
				{{ t('spreed', 'Promote to moderator') }}
			</ActionButton>
			<ActionButton
				icon="icon-delete"
				@click="removeParticipant">
				{{ t('spreed', 'Remove participant') }}
			</ActionButton>
		</Actions>
		<div v-if="isSelected" class="icon-checkmark participant-row__utils utils__checkmark" />
	</li>
</template>

<script>

import ActionButton from '@nextcloud/vue/dist/Components/ActionButton'
import Actions from '@nextcloud/vue/dist/Components/Actions'
import Microphone from 'vue-material-design-icons/Microphone'
import Phone from 'vue-material-design-icons/Phone'
import Video from 'vue-material-design-icons/Video'
import { CONVERSATION, PARTICIPANT } from '../../../../../constants'
import UserStatus from '../../../../../mixins/userStatus'
import isEqual from 'lodash/isEqual'
import AvatarWrapper from '../../../../AvatarWrapper/AvatarWrapper'

export default {
	name: 'Participant',

	components: {
		Actions,
		ActionButton,
		AvatarWrapper,
		Microphone,
		Phone,
		Video,
	},

	mixins: [
		UserStatus,
	],

	props: {
		participant: {
			type: Object,
			required: true,
		},
		showUserStatus: {
			type: Boolean,
			default: true,
		},
		// Toggles the bulk selection state of this component
		isSelectable: {
			type: Boolean,
			default: false,
		},
	},

	computed: {
		/**
		 * Check if the current participant belongs to the selected participants array
		 * in the store
		 * @returns {boolean}
		 */
		isSelected() {
			if (this.isSelectable) {
				let isSelected = false
				this.$store.getters.selectedParticipants.forEach(selectedParticipant => {
					if (isEqual(selectedParticipant, this.participant)) {
						isSelected = true
					}
				})
				return isSelected
			}
			return false
		},
		/**
		 * If the Participant component is used as to display a search result, it will
		 * return true. We use this not to display actions on the searched contacts and
		 * groups.
		 * @returns {boolean}
		 */
		isSearched() {
			return this.participant.userId === undefined
		},
		computedName() {
			if (!this.isSearched) {
				const displayName = this.participant.displayName.trim()

				if (displayName === '' && this.isGuest) {
					return t('spreed', 'Guest')
				}

				if (displayName === '') {
					return t('spreed', '[Unknown username]')
				}

				return displayName
			}
			return this.participant.label
		},
		computedId() {
			if (!this.isSearched) {
				return this.participant.userId
			}
			return this.participant.id
		},
		id() {
			return this.participant.id
		},
		label() {
			return this.participant.label
		},
		callIcon() {
			if (this.isSearched || this.participant.inCall === PARTICIPANT.CALL_FLAG.DISCONNECTED) {
				return ''
			}
			const withVideo = this.participant.inCall & PARTICIPANT.CALL_FLAG.WITH_VIDEO
			if (withVideo) {
				return 'video'
			}
			const withPhone = this.participant.inCall & PARTICIPANT.CALL_FLAG.WITH_PHONE
			if (withPhone) {
				return 'phone'
			}
			return 'audio'
		},
		participantType() {
			return this.participant.participantType
		},
		sessionId() {
			return this.participant.sessionId
		},
		lastPing() {
			return this.participant.lastPing
		},
		token() {
			return this.$store.getters.getToken()
		},
		currentParticipant() {
			return this.$store.getters.conversation(this.token) || {
				sessionId: '0',
				participantType: this.$store.getters.getUserId() !== null ? PARTICIPANT.TYPE.USER : PARTICIPANT.TYPE.GUEST,
			}
		},
		conversation() {
			return this.$store.getters.conversation(this.token) || {
				type: CONVERSATION.TYPE.GROUP,
			}
		},

		isSelf() {
			// User
			if (this.userId) {
				return this.$store.getters.getUserId() === this.userId
			}

			// Guest
			return this.sessionId !== '0' && this.sessionId === this.currentParticipant.sessionId
		},
		selfIsModerator() {
			return this.participantTypeIsModerator(this.currentParticipant.participantType)
		},

		isOffline() {
			return /* this.participant.status === 'offline' || */ this.sessionId === '0'
		},
		isGuest() {
			return [PARTICIPANT.TYPE.GUEST, PARTICIPANT.TYPE.GUEST_MODERATOR].indexOf(this.participantType) !== -1
		},
		isModerator() {
			return this.participantTypeIsModerator(this.participantType)
		},
		showModeratorLabel() {
			return this.isModerator
				&& [CONVERSATION.TYPE.ONE_TO_ONE, CONVERSATION.TYPE.CHANGELOG].indexOf(this.conversation.type) === -1
		},
		canModerate() {
			return this.participantType !== PARTICIPANT.TYPE.OWNER && !this.isSelf && this.selfIsModerator
		},
		canBeDemoted() {
			return this.canModerate
				&& [PARTICIPANT.TYPE.MODERATOR, PARTICIPANT.TYPE.GUEST_MODERATOR].indexOf(this.participantType) !== -1
		},
		canBePromoted() {
			return this.canModerate && !this.isModerator
		},

		participantIdentifier() {
			let data = {}
			if (this.isGuest) {
				data = {
					sessionId: this.sessionId,
				}
			} else {
				data = {
					userId: this.computedId,
				}
			}
			return data
		},

	},

	methods: {
		// Used to allow selecting participants in a search.
		handleClick() {
			if (this.isSearched) {
				this.$emit('clickParticipant', this.participant)
			}
		},
		participantTypeIsModerator(participantType) {
			return [PARTICIPANT.TYPE.OWNER, PARTICIPANT.TYPE.MODERATOR, PARTICIPANT.TYPE.GUEST_MODERATOR].indexOf(participantType) !== -1
		},

		async promoteToModerator() {
			await this.$store.dispatch('promoteToModerator', {
				token: this.token,
				participantIdentifier: this.participantIdentifier,
			})
		},
		async demoteFromModerator() {
			await this.$store.dispatch('demoteFromModerator', {
				token: this.token,
				participantIdentifier: this.participantIdentifier,
			})
		},
		async removeParticipant() {
			await this.$store.dispatch('removeParticipant', {
				token: this.token,
				participantIdentifier: this.participantIdentifier,
			})
		},
	},
}
</script>

<style lang="scss" scoped>
.selected {
	background-color: var(--color-primary-light);
	border-radius: 5px;
}

.participant-row {
	display: flex;
	align-items: center;
	cursor: pointer;
	margin: 4px 0;
	border-radius: 22px;
	height: 56px;
	padding: 0 4px;

	&__user-wrapper {
		margin-top: -4px;
		margin-left: 12px;
		width: calc(100% - 96px);
		display: flex;
		flex-direction: column;
	}
	&__user-name {
		display: inline-block;
		vertical-align: middle;
		line-height: normal;
		cursor: pointer;
	}
	&__guest-indicator,
	&__moderator-indicator {
		color: var(--color-text-maxcontrast);
		font-weight: 300;
		padding-left: 5px;
	}
	&__status {
		color: var(--color-text-maxcontrast);
		line-height: 1.3em;
		overflow: hidden;
		text-overflow: ellipsis;
		white-space: nowrap;
	}
	&__icon {
		width: 44px;
		height: 44px;
		cursor: pointer;
	}
	&__utils {
		margin-right: 28px;
	}

	&__callstate-icon {
		opacity: .4;
		display: inline-block;
		height: 44px;
		width: 44px;
	}
}

.utils {
	&__checkmark {
		margin-right: 11px;
	}
}

.offline {

	.participant-row__user-descriptor > span {
		color: var(--color-text-maxcontrast);
	}
}

</style>
