<!--
  - @copyright Copyright (c) 2023 Maksim Sukharev <antreesy.web@gmail.com>
  -
  - @author Maksim Sukharev <antreesy.web@gmail.com>
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
	<NcActions v-tooltip="t('spreed', 'Send a reaction')"
		:aria-label="t('spreed', 'Send a reaction')"
		:container="container"
		class="reaction">
		<template #icon>
			<EmoticonOutline :size="20"
				fill-color="#ffffff" />
		</template>

		<NcActionButtonGroup class="reaction__group"
			:style="{'--reactions-in-single-row': reactionsInSingleRow}">
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
import EmoticonOutline from 'vue-material-design-icons/EmoticonOutline.vue'

import { emit } from '@nextcloud/event-bus'

import NcActionButton from '@nextcloud/vue/dist/Components/NcActionButton.js'
import NcActionButtonGroup from '@nextcloud/vue/dist/Components/NcActionButtonGroup.js'
import NcActions from '@nextcloud/vue/dist/Components/NcActions.js'

export default {
	name: 'ReactionMenu',

	components: {
		NcActions,
		NcActionButton,
		NcActionButtonGroup,
		EmoticonOutline,
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
		container() {
			return this.$store.getters.getMainContainerSelector()
		},

		reactionsInSingleRow() {
			return Math.ceil(this.supportedReactions.length / 2)
		},
	},

	methods: {
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
