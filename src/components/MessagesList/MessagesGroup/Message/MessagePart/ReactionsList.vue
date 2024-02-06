<!--
  - @copyright Copyright (c) 2024 Dorra Jaouad <dorra.jaoued7@gmail.com>
  -
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
						:class="{'active' : reactionFilter === reaction}"
						type="tertiary"
						@click="handleTabClick(reaction)">
						{{ (reaction === 'all' ? t('spreed', 'All') : reaction) + ' ' + reactionsOverview[reaction].length }}
					</NcButton>
				</div>
				<div class="scrollable">
					<NcListItemIcon v-for="item in reactionsOverview[reactionFilter]"
						:key="item.actorId + item.actorType"
						:name="item.actorDisplayName">
						<span>
							{{ item.reaction?.join('') ?? reactionFilter }}
						</span>
					</NcListItemIcon>
				</div>
			</template>
			<NcLoadingIcon v-else :size="64" />
		</div>
	</NcModal>
</template>

<script>
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcListItemIcon from '@nextcloud/vue/dist/Components/NcListItemIcon.js'
import NcLoadingIcon from '@nextcloud/vue/dist/Components/NcLoadingIcon.js'
import NcModal from '@nextcloud/vue/dist/Components/NcModal.js'

import { ATTENDEE } from '../../../../../constants.js'
import { useGuestNameStore } from '../../../../../stores/guestName.js'

export default {

	name: 'ReactionsList',

	components: {
		NcModal,
		NcLoadingIcon,
		NcListItemIcon,
		NcButton,
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
			guestNameStore: useGuestNameStore(),
		}
	},

	data() {
		return {
			reactionFilter: 'All',
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

			return { All: Object.values(mergedReactionsMap), ...modifiedDetailedReactions }
		},

		reactionsMenu() {
			return ['All', ...this.reactionsSorted]
		},
	},

	methods: {
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
	border-bottom: 1px solid var(--color-background-darker);
}

.scrollable {
	overflow-y: auto;
	overflow-x: hidden;
	height: calc(450px - 123px); // 123px is the height of the header 105px and the footer 18px
}

:deep(.button-vue) {
	border-radius: var(--border-radius-large);

	&.active {
		background-color: var(--color-primary-element-light);
	}
}
</style>
