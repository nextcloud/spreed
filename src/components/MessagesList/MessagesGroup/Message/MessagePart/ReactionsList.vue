<!--
  - SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcModal size="small"
		:container="container"
		@close="closeModal">
		<div class="reactions__modal">
			<h2>
				{{ t('spreed', 'Reactions') }}
			</h2>
			<template v-if="Object.keys(reactionsOverview).length > 0">
				<div class="reactions-list__navigation">
					<NcButton v-for="reaction in reactionsMenu"
						:key="reaction"
						:class="{'active' : reactionFilter === reaction, 'all-reactions__button': reaction === '♡'}"
						type="tertiary"
						@click="handleTabClick(reaction)">
						<HeartOutlineIcon v-if="reaction === '♡'" :size="15" />
						<span v-else>
							{{ reaction }}
						</span>
						{{ reactionsOverview[reaction].length }}
					</NcButton>
				</div>
				<ul class="reactions-list__scrollable">
					<li v-for="item in reactionsOverview[reactionFilter]"
						:key="item.actorId + item.actorType"
						class="reactions-item">
						<AvatarWrapper :id="item.actorId"
							:token="token"
							:name="item.actorDisplayName"
							:source="item.actorType"
							:size="AVATAR.SIZE.SMALL"
							disable-menu />
						<span class="reactions-item__name">{{ item.actorDisplayName }}</span>
						<span class="reactions-item__emojis">
							{{ item.reaction?.join('') ?? reactionFilter }}
						</span>
					</li>
				</ul>
			</template>
			<NcLoadingIcon v-else :size="64" />
		</div>
	</NcModal>
</template>

<script>
import HeartOutlineIcon from 'vue-material-design-icons/HeartOutline.vue'

import { t } from '@nextcloud/l10n'

import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcLoadingIcon from '@nextcloud/vue/dist/Components/NcLoadingIcon.js'
import NcModal from '@nextcloud/vue/dist/Components/NcModal.js'

import AvatarWrapper from '../../../../AvatarWrapper/AvatarWrapper.vue'

import { ATTENDEE, AVATAR } from '../../../../../constants.js'
import { useGuestNameStore } from '../../../../../stores/guestName.js'

export default {

	name: 'ReactionsList',

	components: {
		AvatarWrapper,
		NcModal,
		NcLoadingIcon,
		NcButton,
		HeartOutlineIcon,
	},

	props: {
		token: {
			type: String,
			required: true,
		},

		detailedReactions: {
			type: Object,
			default: () => {},
		},

		reactionsSorted: {
			type: Array,
			default: () => [],
		},
	},

	emits: ['close'],

	setup() {
		return {
			AVATAR,
			guestNameStore: useGuestNameStore(),
		}
	},

	data() {
		return {
			reactionFilter: '♡',
		}
	},

	computed: {
		container() {
			return this.$store.getters.getMainContainerSelector()
		},

		reactionsOverview() {
			const mergedReactionsMap = {}
			const modifiedDetailedReactions = {}

			Object.entries(this.detailedReactions).forEach(([reaction, actors]) => {
				modifiedDetailedReactions[reaction] = []
				actors.forEach(actor => {
					const key = `${actor.actorId}-${actor.actorType}`
					const actorDisplayName = this.getDisplayNameForReaction(actor)

					modifiedDetailedReactions[reaction].push({
						...actor,
						actorDisplayName
					})

					if (mergedReactionsMap[key]) {
						mergedReactionsMap[key].reaction.push(reaction)
					} else {
						mergedReactionsMap[key] = {
							actorDisplayName,
							actorId: actor.actorId,
							actorType: actor.actorType,
							reaction: [reaction]
						}
					}
				})
			})

			return { '♡': Object.values(mergedReactionsMap), ...modifiedDetailedReactions }
		},

		reactionsMenu() {
			return ['♡', ...this.reactionsSorted]
		},
	},

	methods: {
		t,
		closeModal() {
			this.$emit('close')
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

		handleTabClick(reaction) {
			this.reactionFilter = reaction
		},
	},
}
</script>
<style lang="scss" scoped>
.reactions__modal{
	min-height: 450px;
	padding: 18px;
}
.reactions-list__navigation {
	display: flex;
	gap: 2px;
	flex-wrap: wrap;

	:deep(.button-vue) {
		border-radius: var(--border-radius-large);
		&.active {
			background-color: var(--color-primary-element-light);
		}
	}
}

.all-reactions__button :deep(.button-vue__text) {
	display: inline-flex;
	gap: 4px;
}

.reactions-list__scrollable {
	overflow-y: auto;
	overflow-x: hidden;
	height: calc(450px - 123px); // 123px is the height of the header 105px and the footer 18px
}

.reactions-item {
	display: flex;
	align-items: center;
	gap: 8px;
	width: 100%;
	padding: 6px 0;

	&__name {
		white-space: nowrap;
		overflow: hidden;
		text-overflow: ellipsis;
	}

	&__emojis {
		margin-left: auto;
		max-width: 180px;
		direction: rtl;
		display: -webkit-box;
		-webkit-line-clamp: 2;
		-webkit-box-orient: vertical;
		overflow: hidden;
		position: relative;
	}
}
</style>
