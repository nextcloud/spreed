<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div v-show="showIndicatorMessage" class="indicator">
		<div class="indicator__wrapper">
			<div class="indicator__avatars">
				<AvatarWrapper v-for="(participant, index) in visibleParticipants"
					:id="participant.actorId"
					:key="index"
					:token="token"
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

import { t, n } from '@nextcloud/l10n'

import AvatarWrapper from '../AvatarWrapper/AvatarWrapper.vue'

import { AVATAR } from '../../constants.ts'
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
		t,
		n,
		prepareNamesList() {
			return this.visibleParticipants.reverse()
				.map((participant) => this.getParticipantName(participant))
				.map((name) => name ? `<strong>${escapeHtml(name)}</strong>` : undefined)
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
@import '../../assets/variables';

.indicator {
	width: 100%;
	padding-inline-end: 12px;
	margin-bottom: 4px;

	&__wrapper {
		max-width: $messages-input-max-width;
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
		padding-inline-start: 8px;
	}

	&__main {
		width: 100%;
		padding-inline-start: 8px;
	}
}
</style>
