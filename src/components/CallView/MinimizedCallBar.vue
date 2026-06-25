<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script setup lang="ts">
import type { Conversation } from '../../types/index.ts'

import { t } from '@nextcloud/l10n'
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import { useStore } from 'vuex'
import NcButton from '@nextcloud/vue/components/NcButton'
import IconArrowExpand from 'vue-material-design-icons/ArrowExpand.vue'
import IconPhoneHangup from 'vue-material-design-icons/PhoneHangup.vue'
import IconPhoneInTalk from 'vue-material-design-icons/PhoneInTalk.vue'
import CallTime from '../TopBar/CallTime.vue'
import LocalAudioControlButton from './shared/LocalAudioControlButton.vue'
import { useActorStore } from '../../stores/actor.ts'
import { useCallViewStore } from '../../stores/callView.ts'
import { useTokenStore } from '../../stores/token.ts'
import { localMediaModel } from '../../utils/webrtc/index.js'

const router = useRouter()
const store = useStore()
const actorStore = useActorStore()
const callViewStore = useCallViewStore()
const tokenStore = useTokenStore()

const token = computed<string>(() => callViewStore.activeCallToken)
const conversation = computed<Conversation | undefined>(() => store.getters.conversation(token.value))

/**
 * Navigate back to the conversation the call is running in, which restores the
 * full-size call view and dismisses this bar.
 */
function returnToCall() {
	router.push({ name: 'conversation', params: { token: token.value } })
}

/**
 * Leave the active call. The leaveCall store action clears the active call
 * token, which dismisses this bar. The actor session is kept on the call's
 * conversation while minimized, so participantIdentifier targets it correctly.
 */
async function leaveCall() {
	const callToken = token.value
	// The conversation currently being browsed was opened chat-only (no active
	// session, so the global session stayed on the call). Remember it before
	// leaving so we can give it a real session afterwards.
	const openToken = tokenStore.token

	callViewStore.setSelectedVideoPeerId(null)
	await store.dispatch('leaveCall', {
		token: callToken,
		participantIdentifier: actorStore.participantIdentifier,
		all: false,
	})

	// Now that the call is gone (leaveCall cleared the active call token), the
	// central guard in joinConversation no longer forces chat-only, so this
	// establishes a real session for the conversation we are viewing. Without
	// it the actor session stays on the (now left) call and starting a call
	// here would fail. Done here exactly once; cannot race the start-call flow
	// (confirmLeaveMinimizedCall) as those are distinct user actions.
	if (openToken && openToken !== callToken) {
		await store.dispatch('joinConversation', { token: openToken })
	}
}
</script>

<template>
	<div class="minimized-call-bar">
		<button
			class="minimized-call-bar__main"
			:aria-label="t('spreed', 'Return to call')"
			:title="t('spreed', 'Return to call')"
			@click="returnToCall">
			<IconPhoneInTalk :size="20" class="minimized-call-bar__icon" />
			<span class="minimized-call-bar__label">
				{{ t('spreed', 'Ongoing call in {name}', { name: conversation?.displayName ?? '' }) }}
			</span>
			<CallTime v-if="conversation" :start="conversation.callStartTime" class="minimized-call-bar__time" />
		</button>

		<div class="minimized-call-bar__controls">
			<LocalAudioControlButton
				v-if="conversation"
				:token="token"
				:conversation="conversation"
				:model="localMediaModel"
				variant="tertiary"
				disableKeyboardShortcuts />
			<NcButton
				variant="tertiary"
				@click="returnToCall">
				<template #icon>
					<IconArrowExpand :size="20" />
				</template>
				{{ t('spreed', 'Return to call') }}
			</NcButton>
			<NcButton
				:aria-label="t('spreed', 'Leave call')"
				:title="t('spreed', 'Leave call')"
				variant="error"
				@click="leaveCall">
				<template #icon>
					<IconPhoneHangup :size="20" />
				</template>
			</NcButton>
		</div>
	</div>
</template>

<style lang="scss" scoped>
@use '../../assets/variables.scss' as *;

// The bar uses the same dark "call chrome" as the rest of the call UI, so
// white text/icons keep full contrast and the red leave button stands out
// (matches the "return to call" banner pattern in Teams/Meet/Zoom).
.minimized-call-bar {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: var(--default-grid-baseline);

	width: 100%;
	height: var(--call-bar-height);
	padding-inline: var(--default-grid-baseline);

	color: #ffffff;
	background-color: $color-call-background;
}

.minimized-call-bar__main {
	display: flex;
	align-items: center;
	gap: var(--default-grid-baseline);

	min-width: 0;
	height: var(--default-clickable-area);
	padding-inline: var(--default-grid-baseline);
	border: none;
	border-radius: var(--border-radius-element);

	color: inherit;
	background-color: transparent;
	cursor: pointer;

	&:hover,
	&:focus-visible {
		background-color: rgba(255, 255, 255, 0.1);
	}
}

.minimized-call-bar__icon {
	flex: 0 0 auto;
}

.minimized-call-bar__label {
	overflow: hidden;
	white-space: nowrap;
	text-overflow: ellipsis;
	font-weight: 600;
}

.minimized-call-bar__time {
	flex: 0 0 auto;
	opacity: 0.85;
}

.minimized-call-bar__controls {
	display: flex;
	align-items: center;
	gap: calc(0.5 * var(--default-grid-baseline));
	flex: 0 0 auto;

	// White-on-dark for the reused tertiary control buttons (mute, return),
	// with a translucent-white hover/focus consistent with the call chrome.
	:deep(.button-vue--vue-tertiary),
	:deep(.button-vue--tertiary) {
		color: #ffffff;

		&:hover,
		&:focus-visible {
			background-color: rgba(255, 255, 255, 0.1) !important;
		}
	}
}

// On mobile only the timer + controls fit; the long label collapses.
@media (max-width: 768px) {
	.minimized-call-bar__label {
		display: none;
	}
}
</style>
