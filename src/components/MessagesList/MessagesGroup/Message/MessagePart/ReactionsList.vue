<!--
  - SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcDialog :name="t('spreed', 'Reactions')"
		close-on-click-outside
		@update:open="closeModal">
		<div class="reactions__modal">
			<template v-if="Object.keys(reactionsOverview).length > 0">
				<div class="reactions-list__navigation">
					<NcButton v-for="reaction in reactionsMenu"
						:key="reaction"
						:class="{ active: reactionFilter === reaction, 'all-reactions__button': reaction === '♡' }"
						variant="tertiary"
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
						<span class="reactions-item__name">{{ item.actorDisplayNameWithFallback }}</span>
						<span class="reactions-item__emojis">
							{{ item.reaction?.join('') ?? reactionFilter }}
						</span>
					</li>
				</ul>
			</template>
			<NcLoadingIcon v-else :size="64" />
		</div>
	</NcDialog>
</template>

<script>
import { t } from '@nextcloud/l10n'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import HeartOutlineIcon from 'vue-material-design-icons/HeartOutline.vue'
import AvatarWrapper from '../../../../AvatarWrapper/AvatarWrapper.vue'
import { ATTENDEE, AVATAR } from '../../../../../constants.ts'
import { useGuestNameStore } from '../../../../../stores/guestName.js'
import { getDisplayNameWithFallback } from '../../../../../utils/getDisplayName.ts'

export default {

	name: 'ReactionsList',

	components: {
		AvatarWrapper,
		NcButton,
		NcDialog,
		NcLoadingIcon,
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
		reactionsOverview() {
			const mergedReactionsMap = {}
			const modifiedDetailedReactions = {}

			Object.entries(this.detailedReactions).forEach(([reaction, actors]) => {
				modifiedDetailedReactions[reaction] = []
				actors.forEach((actor) => {
					const key = `${actor.actorId}-${actor.actorType}`
					const actorDisplayName = this.getDisplayNameForReaction(actor)
					const actorDisplayNameWithFallback = getDisplayNameWithFallback(actorDisplayName, actor.actorType)

					modifiedDetailedReactions[reaction].push({
						...actor,
						actorDisplayName,
						actorDisplayNameWithFallback,
					})

					if (mergedReactionsMap[key]) {
						mergedReactionsMap[key].reaction.push(reaction)
					} else {
						mergedReactionsMap[key] = {
							actorDisplayName,
							actorDisplayNameWithFallback,
							actorId: actor.actorId,
							actorType: actor.actorType,
							reaction: [reaction],
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

			return reactingParticipant.actorDisplayName.trim()
		},

		handleTabClick(reaction) {
			this.reactionFilter = reaction
		},
	},
}
</script>

<style lang="scss" scoped>
.reactions__modal {
	min-height: 450px;
	padding-bottom: calc(3 * var(--default-grid-baseline));
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
		margin-inline-start: auto;
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
