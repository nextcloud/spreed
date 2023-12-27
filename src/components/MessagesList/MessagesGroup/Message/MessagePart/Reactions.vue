<!--
  - @copyright Copyright (c) 2023 Dorra Jaouad <dorra.jaoued7@gmail.com>
  -
  - @author Marco Ambrosini <marcoambrosini@icloud.com>
  - @author Dorra Jaouad <dorra.jaoued7@gmail.com>
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
	<!-- reactions buttons and popover with details -->
	<div class="reactions-wrapper">
		<NcPopover v-for="reaction in Object.keys(detailedReactions ?? plainReactions)"
			:key="reaction"
			:delay="200"
			:focus-trap="false"
			:triggers="['hover']"
			@after-show="fetchReactions">
			<template #trigger>
				<NcButton :type="userHasReacted(reaction) ? 'primary' : 'secondary'"
					class="reaction-button"
					@click="handleReactionClick(reaction)">
					{{ reaction }} {{ reactionsCount(reaction) }}
				</NcButton>
			</template>

			<div v-if="hasReactions" class="reaction-details">
				<span>{{ getReactionSummary(reaction) }}</span>
			</div>
		</NcPopover>

		<!-- More reactions picker -->
		<NcEmojiPicker v-if="canReact && hasReactions"
			:per-line="5"
			:container="`#message_${id}`"
			@select="handleReactionClick"
			@after-show="emitEmojiPickerStatus"
			@after-hide="emitEmojiPickerStatus">
			<NcButton class="reaction-button"
				:aria-label="t('spreed', 'Add more reactions')">
				<template #icon>
					<EmoticonOutline :size="15" />
				</template>
			</NcButton>
		</NcEmojiPicker>
	</div>
</template>

<script>
import EmoticonOutline from 'vue-material-design-icons/EmoticonOutline.vue'

import { showError } from '@nextcloud/dialogs'

import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcEmojiPicker from '@nextcloud/vue/dist/Components/NcEmojiPicker.js'
import NcPopover from '@nextcloud/vue/dist/Components/NcPopover.js'

import { ATTENDEE } from '../../../../../constants.js'
import { useGuestNameStore } from '../../../../../stores/guestName.js'
import { useReactionsStore } from '../../../../../stores/reactions.js'

export default {
	name: 'Reactions',

	components: {
		NcButton,
		NcEmojiPicker,
		NcPopover,
		EmoticonOutline,
	},

	props: {
		token: {
			type: String,
			required: true,
		},
		/**
		 * Whether the current user can react to the message.
		 */
		canReact: {
			type: Boolean,
			default: false,
		},
		/**
		 * The message id.
		 */
		id: {
			type: [String, Number],
			required: true,
		},
	},

	emits: ['emoji-picker-toggled'],

	setup() {
		const guestNameStore = useGuestNameStore()
		const reactionsStore = useReactionsStore()
		return {
			guestNameStore,
			reactionsStore,
		 }
	},

	computed: {
		hasReactions() {
			return Object.keys(Object(this.detailedReactions)).length !== 0
		},

		detailedReactions() {
			return this.reactionsStore.getReactions(this.token, this.id)
		},

		plainReactions() {
			return this.$store.getters.message(this.token, this.id).reactions
		},

		reactionsSelf() {
			return this.$store.getters.message(this.token, this.id).reactionsSelf
		},

		/**
		 * Whether the plain reactions are different than the detailed ones.
		 */
		hasMoreReactions() {
			return this.hasReactions
					&& Object.keys(this.plainReactions).length !== Object.keys(this.detailedReactions).length
		},
	},

	methods: {
		fetchReactions() {
			if (!this.hasReactions || this.hasMoreReactions) {
				this.reactionsStore.fetchReactions(this.token, this.id)
			}
		},

		userHasReacted(reaction) {
			return this.reactionsSelf?.includes(reaction)
		},

		async handleReactionClick(clickedEmoji) {
			if (!this.canReact) {
				showError(t('spreed', 'No permission to post reactions in this conversation'))
				return
			}

			// Check if current user has already added this reaction to the message
			if (!this.userHasReacted(clickedEmoji)) {
				this.$store.dispatch('addReactionToMessage', {
					token: this.token,
					messageId: this.id,
					selectedEmoji: clickedEmoji,
				})
			} else {
				this.$store.dispatch('removeReactionFromMessage', {
					token: this.token,
					messageId: this.id,
					selectedEmoji: clickedEmoji,
				})
			}
		},

		getDisplayNameForReaction(reactingParticipant) {
			if (reactingParticipant.actorType === ATTENDEE.ACTOR_TYPE.GUESTS) {
				return this.guestNameStore.getGuestNameWithGuestSuffix(this.token, reactingParticipant.actorId)
			}

			const displayName = reactingParticipant.actorDisplayName.trim()
			if (displayName === '') {
				return t('spreed', 'Deleted user')
			}

			return displayName
		},

		reactionsCount(reaction) {
			return this.detailedReactions
				? this.detailedReactions[reaction]?.length
				: this.plainReactions[reaction]
		},

		getReactionSummary(reaction) {
			// Check if the reaction details are loaded
			if (!this.hasReactions) {
				return ''
			}
			const list = this.detailedReactions[reaction]
			const summary = []

			for (const item in list) {
				if (list[item].actorType === this.$store.getters.getActorType()
					&& list[item].actorId === this.$store.getters.getActorId()) {
					summary.unshift(t('spreed', 'You'))
				} else {
					summary.push(this.getDisplayNameForReaction(list[item]))
				}
			}

			return summary.join(', ')
		},

		emitEmojiPickerStatus() {
			this.$emit('emoji-picker-toggled')
		},
	}
}
</script>
<style lang="scss" scoped>
.reactions-wrapper {
	display: flex;
	flex-wrap: wrap;
	margin: 4px 175px 4px -2px;
}
.reaction-button {
	// Clear server rules
	min-height: 0 !important;
	margin: 2px;
	height: 26px;
	padding: 0 6px;

	:deep(.button-vue__text) {
		font-weight: normal;
	}
}

.reaction-details {
	padding: 8px;
}

</style>
