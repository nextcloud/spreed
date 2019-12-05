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
	<li class="participant-row"
		:class="{
			'offline': isOffline,
			'currentUser': isSelf,
			'guestUser': isGuest,
			'selected': isSelected }"
		@click="handleClick">
		<div class="participant-row__avatar-wrapper">
			<Avatar
				:user="computedId"
				:display-name="computedName" />
		</div>

		<span class="participant-row__user-name">{{ computedName }}</span>
		<span v-if="isModerator" class="participant-row__moderator-indicator">({{ t('spreed', 'moderator') }})</span>
		<Actions v-if="canModerate && !isSearched" class="participant-row__actions">
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
	</li>
</template>

<script>

import ActionButton from '@nextcloud/vue/dist/Components/ActionButton'
import Actions from '@nextcloud/vue/dist/Components/Actions'
import Avatar from '@nextcloud/vue/dist/Components/Avatar'
import { PARTICIPANT } from '../../../../../constants'

export default {
	name: 'Participant',

	components: {
		Actions,
		ActionButton,
		Avatar,
	},

	props: {
		participant: {
			type: Object,
			required: true,
		},
	},

	computed: {
		/**
		 * This parameter is automatically computed by the ParticipantsList component
		 * when both component are used in the new-group-conversation-form context
		 * @returns {array}
		 */
		isSelected() {
			return this.participant.selected
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
			if (this.participant.displayName) {
				return this.participant.displayName
			}
			return this.participant.label
		},
		computedId() {
			if (this.participant.userId) {
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
			return this.$route.params.token
		},
		currentParticipant() {
			return this.$store.getters.conversations[this.token] || {
				sessionId: '0',
				participantType: this.$store.getters.getUserId() !== null ? PARTICIPANT.TYPE.USER : PARTICIPANT.TYPE.GUEST,
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
			return this.sessionId === '0'
		},
		isGuest() {
			return [PARTICIPANT.TYPE.GUEST, PARTICIPANT.TYPE.GUEST_MODERATOR].indexOf(this.participantType) !== -1
		},
		isModerator() {
			return this.participantTypeIsModerator(this.participantType)
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
					participant: this.computedId,
				}
			}
			return data
		},

	},

	methods: {
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
	height: 44px;
	cursor: pointer;
	padding: 0 5px;
    margin: 5px 0;
    border-radius: 22px;
	&__avatar-wrapper {
		height: 32px;
		width: 32px;
	}
	&__user-name {
		margin-left: 6px;
		display: inline-block;
		vertical-align: middle;
		line-height: normal;
		cursor: pointer;
	}
	&__moderator-indicator {
		color: var(--color-text-maxcontrast);
		font-weight: 300;
		padding-left: 5px;
	}
	&__icon {
		width: 32px;
		height: 44px;
		cursor: pointer;
	}
	&__actions {
		margin-left: auto;
	}
}

.offline {
	& > .participant-row__avatar-wrapper {
		opacity: .4;
	}
	& > span {
		color: var(--color-text-maxcontrast);
	}
}

</style>
