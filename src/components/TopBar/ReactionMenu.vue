<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcActions type="tertiary"
		:title="t('spreed', 'Send a reaction')"
		:aria-label="t('spreed', 'Send a reaction')"
		class="reaction">
		<template #icon>
			<IconEmoticonOutline :size="20" />
		</template>

		<NcActionButtonGroup class="reaction__group"
			:style="{ '--reactions-in-single-row': reactionsInSingleRow }">
			<NcActionButton v-for="(reaction, index) in supportedReactions"
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

<script>
import IconEmoticonOutline from 'vue-material-design-icons/EmoticonOutline.vue'

import { emit } from '@nextcloud/event-bus'
import { t } from '@nextcloud/l10n'

import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcActionButtonGroup from '@nextcloud/vue/components/NcActionButtonGroup'
import NcActions from '@nextcloud/vue/components/NcActions'

export default {
	name: 'ReactionMenu',

	components: {
		NcActions,
		NcActionButton,
		NcActionButtonGroup,
		IconEmoticonOutline,
	},

	props: {
		/**
		 * The conversation token
		 */
		token: {
			type: String,
			required: true,
		},

		/**
		 * Signalling participant model
		 */
		localCallParticipantModel: {
			type: Object,
			required: true,
		},

		/**
		 * Supported reactions
		 */
		supportedReactions: {
			type: Array,
			validator: (prop) => prop.every(e => typeof e === 'string'),
			required: true,
		},
	},

	data() {
		return {
			throttleTimer: null,
		}
	},

	computed: {
		reactionsInSingleRow() {
			return Math.ceil(this.supportedReactions.length / 2)
		},
	},

	methods: {
		t,
		throttledSendReaction(reaction) {
			if (this.throttleTimer) {
				return
			}

			this.sendReaction(reaction)
			this.throttleTimer = setTimeout(() => {
				this.throttleTimer = null
			}, 2000)
		},

		sendReaction(reaction) {
			// send reaction to other participants
			this.localCallParticipantModel.sendReaction(reaction)

			// show reaction to yourself
			emit('send-reaction', {
				model: this.localCallParticipantModel,
				reaction,
			})
		},
	},
}
</script>

<style lang="scss" scoped>
.reaction {
	&__group {
		:deep(.nc-button-group-content) {
			flex-wrap: wrap;
			justify-content: flex-start;
			gap: 0;
			width: calc(var(--reactions-in-single-row) * var(--default-clickable-area))
		}
	}

	&__button {
		flex: 0 0 calc(100% / var(--reactions-in-single-row)) !important;
	}
}
</style>
