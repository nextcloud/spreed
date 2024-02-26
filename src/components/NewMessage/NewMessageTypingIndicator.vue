<!--
  - @copyright Copyright (c) 2023 Maksim Sukharev <antreesy.web@gmail.com>
  -
  - @author Maksim Sukharev <antreesy.web@gmail.com>
  -
  - @license AGPL-3.0-or-later
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
	<div v-show="showIndicatorMessage" class="indicator">
		<div class="indicator__wrapper">
			<div class="indicator__avatars">
				<AvatarWrapper v-for="(participant, index) in visibleParticipants"
					:id="participant.actorId"
					:key="index"
					:name="participant.displayName"
					:source="participant.actorType"
					:size="AVATAR.SIZE.EXTRA_SMALL"
					condensed
					:condensed-overlap="8"
					disable-menu
					disable-tooltip />
			</div>
			<!-- eslint-disable-next-line vue/no-v-html -->
			<p class="indicator__main" v-html="indicatorMessage" />
		</div>
	</div>
</template>

<script>
import escapeHtml from 'escape-html'

import AvatarWrapper from '../AvatarWrapper/AvatarWrapper.vue'

import { AVATAR } from '../../constants.js'
import { useGuestNameStore } from '../../stores/guestName.js'

export default {
	name: 'NewMessageTypingIndicator',
	components: { AvatarWrapper },

	props: {
		/**
		 * The conversation token
		 */
		token: {
			type: String,
			required: true,
		},
	},

	setup() {
		const guestNameStore = useGuestNameStore()
		return { AVATAR, guestNameStore }
	},

	computed: {
		isGuest() {
			return this.$store.getters.isActorGuest()
		},

		externalTypingSignals() {
			return this.$store.getters.externalTypingSignals(this.token)
		},

		typingParticipants() {
			return this.$store.getters.participantsListTyping(this.token)
		},

		visibleParticipants() {
			return this.typingParticipants.slice(0, 3)
		},

		hiddenParticipantsCount() {
			return this.typingParticipants.slice(3).length
		},

		showIndicatorMessage() {
			return this.isGuest
				? !!this.externalTypingSignals.length
				: !!this.typingParticipants.length
		},

		indicatorMessage() {
			if (this.isGuest) {
				return t('spreed', 'Someone is typing …')
			}

			if (!this.typingParticipants) {
				return ''
			}

			const [user1, user2, user3] = this.prepareNamesList()

			if (this.typingParticipants.length === 1) {
				return t('spreed', '{user1} is typing …',
					{ user1 }, undefined, { escape: false })
			}

			if (this.typingParticipants.length === 2) {
				return t('spreed', '{user1} and {user2} are typing …',
					{ user1, user2 }, undefined, { escape: false })
			}

			if (this.typingParticipants.length === 3) {
				return t('spreed', '{user1}, {user2} and {user3} are typing …',
					{ user1, user2, user3 }, undefined, { escape: false })
			}

			return n('spreed', '{user1}, {user2}, {user3} and %n other are typing …',
				'{user1}, {user2}, {user3} and %n others are typing …',
				this.hiddenParticipantsCount, { user1, user2, user3 }, { escape: false })
		},
	},

	methods: {
		prepareNamesList() {
			return this.visibleParticipants.reverse()
				.map(participant => this.getParticipantName(participant))
				.map(name => name ? `<strong>${escapeHtml(name)}</strong>` : undefined)
		},

		// TODO implement model from signaling here
		getParticipantName(participant) {
			if (participant?.displayName) {
				return participant.displayName
			}

			return this.guestNameStore.getGuestName(this.token, participant.actorId)
		},
	},
}
</script>

<style lang="scss" scoped>
.indicator {
	width: 100%;
	padding-right: 12px;
	margin-bottom: 4px;

	&__wrapper {
		max-width: 800px;
		display: flex;
		align-items: center;
		margin: auto;
		padding: 0;
		line-height: 120%;
		color: var(--color-text-maxcontrast);
	}

	&__avatars {
		display: flex;
		justify-content: center;
		align-items: center;
		flex-direction: row-reverse;
		flex-shrink: 0;
		width: 52px;
		padding-left: 8px;
	}

	&__main {
		width: 100%;
		padding-left: 8px;
	}
}
</style>
