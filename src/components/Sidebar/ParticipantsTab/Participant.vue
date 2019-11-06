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
		:class="{ offline: isOffline, currentUser: isSelf, guestUser: isGuest }">
		<div class="participant-row__avatar-wrapper">
			<Avatar
				:user="userId"
				:display-name="displayName" />
		</div>

		<span class="participant-row__user-name">{{ displayName }}</span>
		<span v-if="isModerator" class="participant-row__moderator-indicator">({{ t('spreed', 'moderator') }})</span>

		<template v-if="canModerate">
			<Actions class="participant-row__actions">
				<ActionButton v-if="canBeDemoted"
					icon="icon-rename"
					@click.prevent.exact="demoteFromModerator">
					{{ t('spreed', 'Demote from moderator') }}
				</ActionButton>
				<ActionButton v-if="canBePromoted"
					icon="icon-rename"
					@click.prevent.exact="promoteToModerator">
					{{ t('spreed', 'Promote to moderator') }}
				</ActionButton>
				<ActionButton
					icon="icon-delete"
					@click.prevent.exact="removeParticipant">
					{{ t('spreed', 'Remove participant') }}
				</ActionButton>
			</Actions>
		</template>
	</li>
</template>

<script>

import ActionButton from '@nextcloud/vue/dist/Components/ActionButton'
import Actions from '@nextcloud/vue/dist/Components/Actions'
import Avatar from '@nextcloud/vue/dist/Components/Avatar'
import { PARTICIPANT } from '../../../constants'
import { getCurrentUser } from '@nextcloud/auth'

export default {
	name: 'Participant',

	components: {
		Actions,
		ActionButton,
		Avatar,
	},

	props: {
		userId: {
			type: String,
			required: true,
		},
		displayName: {
			type: String,
			required: true,
		},
		participantType: {
			type: Number,
			required: true,
		},
		lastPing: {
			type: Number,
			default: 0,
		},
		sessionId: {
			type: String,
			required: true,
		},
	},

	computed: {
		token() {
			return this.$route.params.token
		},
		currentParticipant() {
			return this.$store.getters.conversations[this.token]
		},

		isSelf() {
			// User
			if (this.userId) {
				return getCurrentUser().uid === this.userId
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
					participant: this.userId,
				}
			}

			return data
		},
	},

	methods: {
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

.participant-row {
	display: flex;
	align-items: center;
	height: 44px;
	&__avatar-wrapper {
		height: 32px;
		width: 32px;
	}
	&__user-name {
		margin-left: 6px;
		display: inline-block;
		vertical-align: middle;
		line-height: normal;
	}
	&__moderator-indicator {
		color: var(--color-text-maxcontrast);
		font-weight: 300;
		padding-left: 5px;
	}
	&__icon {
		width: 32px;
		height: 44px;
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
