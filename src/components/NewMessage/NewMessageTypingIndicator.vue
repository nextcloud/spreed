<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div v-show="showIndicatorMessage" class="indicator">
		<div class="indicator__wrapper">
			<div class="indicator__avatars">
				<AvatarWrapper
					v-for="(participant, index) in visibleParticipants"
					:id="participant.actorId"
					:key="index"
					:token="token"
					:name="participant.displayName"
					:source="participant.actorType"
					:size="AVATAR.SIZE.EXTRA_SMALL"
					condensed
					:condensedOverlap="8"
					disableMenu
					disableTooltip />
			</div>
			<!-- eslint-disable-next-line vue/no-v-html -->
			<p class="indicator__main" v-html="indicatorMessage" />
		</div>
	</div>
</template>

<script>
import { n, t } from '@nextcloud/l10n'
import escapeHtml from 'escape-html'
import AvatarWrapper from '../AvatarWrapper/AvatarWrapper.vue'
import { AVATAR } from '../../constants.ts'
import { useActorStore } from '../../stores/actor.ts'
import { useGuestNameStore } from '../../stores/guestName.ts'
import { useParticipantActivityStore } from '../../stores/participantActivity.ts'

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
		const participantActivityStore = useParticipantActivityStore()
		return {
			AVATAR,
			guestNameStore,
			participantActivityStore,
			actorStore: useActorStore(),
		}
	},

	computed: {
		isGuest() {
			return this.actorStore.isActorGuest
		},

		externalTypingSignals() {
			return this.participantActivityStore.externalTypingSignals(this.token)
		},

		/**
		 * Get list of participants filtered to include only those that are currently typing
		 */
		typingParticipants() {
			if (!this.externalTypingSignals.length) {
				return []
			}

			return this.$store.getters.participantsList(this.token).filter((attendee) => {
				// Check if participant's sessionId matches with any of sessionIds from signaling...
				return this.externalTypingSignals.some((sessionId) => attendee.sessionIds.includes(sessionId))
					// ... and it's not the participant with same actorType and actorId as yourself
					&& !this.actorStore.checkIfSelfIsActor(attendee)
			})
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
				return t('spreed', '{user1} is typing …', { user1 }, undefined, { escape: false })
			}

			if (this.typingParticipants.length === 2) {
				return t('spreed', '{user1} and {user2} are typing …', { user1, user2 }, undefined, { escape: false })
			}

			if (this.typingParticipants.length === 3) {
				return t('spreed', '{user1}, {user2} and {user3} are typing …', { user1, user2, user3 }, undefined, { escape: false })
			}

			return n('spreed', '{user1}, {user2}, {user3} and %n other are typing …', '{user1}, {user2}, {user3} and %n others are typing …', this.hiddenParticipantsCount, { user1, user2, user3 }, { escape: false })
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
@use '../../assets/variables.scss' as *;

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
