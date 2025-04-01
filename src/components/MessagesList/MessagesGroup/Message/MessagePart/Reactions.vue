<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<!-- reactions buttons and popover with details -->
	<div v-if="reactionsCount && reactionsSorted" class="reactions-wrapper">
		<NcPopover v-for="reaction in reactionsSorted"
			:key="reaction"
			:delay="200"
			:focus-trap="false"
			:triggers="['hover']"
			:popper-triggers="['hover']"
			@after-show="fetchReactions">
			<template #trigger>
				<NcButton :type="userHasReacted(reaction) ? 'primary' : 'secondary'"
					size="small"
					@click="handleReactionClick(reaction)">
					<span class="reaction-emoji">{{ reaction }}</span> {{ reactionsCount(reaction) }}
				</NcButton>
			</template>

			<div v-if="hasReactionsLoaded" class="reaction-details">
				<span>{{ getReactionSummary(reaction) }}
					<span v-if="reactionsCount(reaction) === 4">
						{{ remainingReactionsLabel(reaction) }}
					</span>
					<a v-else-if="reactionsCount(reaction) > 4"
						class="more-reactions-button"
						role="button"
						tabindex="0"
						@click.prevent="showAllReactions = true">
						{{ remainingReactionsLabel(reaction) }}
					</a>
				</span>
			</div>
			<div v-else class="details-loading">
				<NcLoadingIcon />
			</div>
		</NcPopover>

		<!-- all reactions button -->
		<NcButton v-if="showControls"
			size="small"
			:title="t('spreed', 'Show all reactions')"
			:aria-label="t('spreed', 'Show all reactions')"
			@click="showAllReactions = true">
			<HeartOutlineIcon :size="15" />
		</NcButton>
		<span v-else class="reaction-button--thumbnail" />

		<!-- More reactions picker -->
		<NcEmojiPicker v-if="canReact && showControls"
			:per-line="5"
			@select="handleReactionClick"
			@after-show="emitEmojiPickerStatus"
			@after-hide="emitEmojiPickerStatus">
			<NcButton size="small"
				:title="t('spreed', 'Add more reactions')"
				:aria-label="t('spreed', 'Add more reactions')">
				<template #icon>
					<EmoticonPlusOutline :size="15" />
				</template>
			</NcButton>
		</NcEmojiPicker>
		<span v-else-if="canReact" class="reaction-button--thumbnail" />

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
import { t, n } from '@nextcloud/l10n'

import NcButton from '@nextcloud/vue/components/NcButton'
import NcEmojiPicker from '@nextcloud/vue/components/NcEmojiPicker'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcPopover from '@nextcloud/vue/components/NcPopover'

import ReactionsList from './ReactionsList.vue'

import { ATTENDEE } from '../../../../../constants.ts'
import { useGuestNameStore } from '../../../../../stores/guestName.js'
import { useReactionsStore } from '../../../../../stores/reactions.js'
import { getDisplayNameWithFallback } from '../../../../../utils/getDisplayName.ts'

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
			if (this.detailedReactions) {
				return Object.keys(this.detailedReactions)
					.sort((a, b) => this.detailedReactions[b].length - this.detailedReactions[a].length)
			} else if (this.plainReactions) {
				return Object.keys(this.plainReactions)
					.sort((a, b) => this.plainReactions[b] - this.plainReactions[a])
			}
			return undefined
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
		t,
		n,
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

			return getDisplayNameWithFallback(reactingParticipant.actorDisplayName, reactingParticipant.actorType)
		},

		reactionsCount(reaction) {
			if (!this.detailedReactions || !this.plainReactions) {
				return undefined
			}
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
			const reactionsCount = this.reactionsCount(reaction)
			if (reactionsCount === 4) {
				return t('spreed', 'and {participant}', { participant: this.getDisplayNameForReaction(this.detailedReactions[reaction][3]) })
			}
			return n('spreed', 'and %n other participant', 'and %n other participants', this.reactionsCount(reaction) - 3)
		},
	}
}
</script>
<style lang="scss" scoped>
.reactions-wrapper {
	--minimal-button-width: 48px;
	--font-family-emoji: 'Segoe UI Emoji', 'Segoe UI Symbol', 'Segoe UI', 'Apple Color Emoji', 'Twemoji Mozilla', 'Noto Color Emoji', 'EmojiOne Color', 'Android Emoji';
	display: flex;
	flex-wrap: wrap;
	gap: var(--default-grid-baseline);

	// Overwrite NcButton styles
	:deep(.button-vue) {
		min-width: var(--minimal-button-width);
	}
	:deep(.button-vue__text) {
		font-weight: normal;
	}

	.reaction-emoji {
		font-family: var(--font-family-emoji);
	}

	.reaction-button--thumbnail {
		height: var(--clickable-area-small);
		width: var(--minimal-button-width);
		pointer-events: none;
	}
}

.reaction-details {
	padding: 8px;
	max-width: 250px;
}

.details-loading {
	display: flex;
	justify-content: center;
	width: 38px;
}

.more-reactions-button {
	text-decoration: underline;
	&:hover {
		text-decoration: none;
	}
}
</style>
