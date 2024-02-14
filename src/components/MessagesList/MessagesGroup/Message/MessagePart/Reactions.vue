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
		<NcPopover v-for="reaction in reactionsSorted"
			:key="reaction"
			:delay="200"
			:focus-trap="false"
			:triggers="['hover']"
			:popper-triggers="['hover']"
			@after-show="fetchReactions">
			<template #trigger>
				<NcButton :type="userHasReacted(reaction) ? 'primary' : 'secondary'"
					class="reaction-button"
					@click="handleReactionClick(reaction)">
					{{ reaction }} {{ reactionsCount(reaction) }}
				</NcButton>
			</template>

			<div v-if="hasReactionsLoaded" class="reaction-details">
				<span>{{ getReactionSummary(reaction) }}</span>
				<NcButton v-if="reactionsCount(reaction) > 3"
					type="tertiary-no-background"
					@click="showAllReactions = true">
					{{ remainingReactionsLabel(reaction) }}
				</NcButton>
			</div>
			<div v-else class="details-loading">
				<NcLoadingIcon />
			</div>
		</NcPopover>

		<!-- all reactions button -->
		<NcButton v-if="showControls"
			class="reaction-button"
			:title="t('spreed', 'Show all reactions')"
			@click="showAllReactions = true">
			<HeartOutlineIcon :size="15" />
		</NcButton>

		<!-- More reactions picker -->
		<NcEmojiPicker v-if="canReact && showControls"
			:per-line="5"
			:container="`#message_${id}`"
			@select="handleReactionClick"
			@after-show="emitEmojiPickerStatus"
			@after-hide="emitEmojiPickerStatus">
			<NcButton class="reaction-button"
				:title="t('spreed', 'Add more reactions')"
				:aria-label="t('spreed', 'Add more reactions')">
				<template #icon>
					<EmoticonPlusOutline :size="15" />
				</template>
			</NcButton>
		</NcEmojiPicker>

		<!-- all reactions modal-->
		<ReactionsList v-if="showAllReactions"
			:token="token"
			:detailed-reactions="detailedReactions"
			:reactions-sorted="reactionsSorted"
			@close="showAllReactions = false" />
	</div>
</template>

<script>
import EmoticonPlusOutline from 'vue-material-design-icons/EmoticonPlusOutline.vue'
import HeartOutlineIcon from 'vue-material-design-icons/HeartOutline.vue'

import { showError } from '@nextcloud/dialogs'

import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcEmojiPicker from '@nextcloud/vue/dist/Components/NcEmojiPicker.js'
import NcLoadingIcon from '@nextcloud/vue/dist/Components/NcLoadingIcon.js'
import NcPopover from '@nextcloud/vue/dist/Components/NcPopover.js'

import ReactionsList from './ReactionsList.vue'

import { ATTENDEE } from '../../../../../constants.js'
import { useGuestNameStore } from '../../../../../stores/guestName.js'
import { useReactionsStore } from '../../../../../stores/reactions.js'

export default {
	name: 'Reactions',

	components: {
		NcButton,
		NcEmojiPicker,
		NcLoadingIcon,
		NcPopover,
		ReactionsList,
		EmoticonPlusOutline,
		HeartOutlineIcon,
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

		showControls: {
			type: Boolean,
			default: false,
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

	data() {
		return {
			showAllReactions: false,
		}
	},

	computed: {
		hasReactionsLoaded() {
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

		reactionsSorted() {
			return this.detailedReactions
				? Object.keys(this.detailedReactions)
					.sort((a, b) => this.detailedReactions[b].length - this.detailedReactions[a].length)
				: Object.keys(this.plainReactions)
					.sort((a, b) => this.plainReactions[b] - this.plainReactions[a])
		},

		/**
		 * Compare the plain reactions with the simplified detailed reactions.
		 */
		hasOutdatedDetails() {
			const detailedReactionsSimplified = Object.fromEntries(
				Object.entries(this.detailedReactions)
					.sort() // Plain reactions come sorted
					.map(([key, value]) => [key, value.length])
			)
			return this.hasReactionsLoaded
					&& JSON.stringify(this.plainReactions) !== JSON.stringify(detailedReactionsSimplified)
		},
	},

	methods: {
		fetchReactions() {
			if (!this.hasReactionsLoaded || this.hasOutdatedDetails) {
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
				this.reactionsStore.addReactionToMessage({
					token: this.token,
					messageId: this.id,
					selectedEmoji: clickedEmoji,
				})
			} else {
				this.reactionsStore.removeReactionFromMessage({
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
			if (!this.hasReactionsLoaded) {
				return ''
			}
			const list = this.detailedReactions[reaction].slice(0, 3)
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

		remainingReactionsLabel(reaction) {
			return n('spreed', 'and %n other participant', 'and %n other participants', this.reactionsCount(reaction) - 3)
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

.details-loading {
	display: flex;
	justify-content: center;
	width: 38px;
}

</style>
