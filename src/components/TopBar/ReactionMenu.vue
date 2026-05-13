<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script setup lang="ts">
import type { LocalCallParticipantModel } from '../../types/index.ts'

import { emit } from '@nextcloud/event-bus'
import { t } from '@nextcloud/l10n'
import { computed } from 'vue'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcActionButtonGroup from '@nextcloud/vue/components/NcActionButtonGroup'
import NcActions from '@nextcloud/vue/components/NcActions'
import IconEmoticonOutline from 'vue-material-design-icons/EmoticonOutline.vue'

const props = defineProps<{
	/* The conversation token */
	token: string
	/* Signaling participant model */
	localCallParticipantModel: LocalCallParticipantModel
	/* Supported reactions */
	supportedReactions: string[]
}>()

let throttleTimer: NodeJS.Timeout | undefined

const reactionsInSingleRow = computed(() => Math.ceil(props.supportedReactions.length / 2))

/**
 * Throttle reaction sending from a single use (to 1 reaction every 2 seconds)
 *
 * @param reaction emoji character to send
 */
function throttledSendReaction(reaction: string) {
	if (throttleTimer) {
		return
	}

	sendReaction(reaction)
	throttleTimer = setTimeout(() => {
		throttleTimer = undefined
	}, 2_000)
}

/**
 * Relay reaction via WebRTC and render on sender screen
 *
 * @param reaction emoji character to send
 */
function sendReaction(reaction: string) {
	// send reaction to other participants
	props.localCallParticipantModel.sendReaction(reaction)

	// show reaction to yourself
	emit('send-reaction', {
		model: props.localCallParticipantModel,
		reaction,
	})
}
</script>

<template>
	<NcActions
		variant="tertiary"
		:title="t('spreed', 'Send a reaction')"
		:aria-label="t('spreed', 'Send a reaction')"
		class="reaction">
		<template #icon>
			<IconEmoticonOutline :size="20" />
		</template>

		<NcActionButtonGroup
			class="reaction__group"
			:style="{ '--reactions-in-single-row': reactionsInSingleRow }">
			<NcActionButton
				v-for="(reaction, index) in supportedReactions"
				:key="index"
				:aria-label="t('spreed', 'React with {reaction}', { reaction })"
				class="reaction__button"
				@click="throttledSendReaction(reaction)">
				<template #icon>
					{{ reaction }}
				</template>
			</NcActionButton>
		</NcActionButtonGroup>
	</NcActions>
</template>

<style lang="scss" scoped>
.reaction {
	&__group {
		// Override NcActionButtonGroup styles to fit reactions in a compact way
		:deep(.nc-button-group-content) {
			flex-wrap: wrap;
			justify-content: flex-start;
			gap: 0 !important;
			width: calc(var(--reactions-in-single-row) * var(--default-clickable-area))
		}
	}

	&__button {
		flex: 0 0 calc(100% / var(--reactions-in-single-row)) !important;
	}
}
</style>
