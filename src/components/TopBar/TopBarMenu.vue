<!--
  - @copyright Copyright (c) 2022 Marco Ambrosini <marcoambrosini@icloud.com>
  -
  - @author Marco Ambrosini <marcoambrosini@icloud.com>
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
	<NcActions v-if="showActions && isInCall"
		v-tooltip="t('spreed', 'More actions')"
		:container="container"
		:aria-label="t('spreed', 'More actions')">
		<template #icon>
			<DotsHorizontal :size="20"
				fill-color="#ffffff" />
		</template>
		<NcActionButton :close-after-click="true"
			@click="toggleHandRaised">
			<!-- The following icon is much bigger than all the others
						so we reduce its size -->
			<template #icon>
				<HandBackLeft :size="18" />
			</template>
			{{ raiseHandButtonLabel }}
		</NcActionButton>
		<NcActionButton v-if="isVirtualBackgroundAvailable"
			:close-after-click="true"
			@click="toggleVirtualBackground">
			<template #icon>
				<BlurOff v-if="isVirtualBackgroundEnabled"
					:size="20" />
				<Blur v-else
					:size="20" />
			</template>
			{{ toggleVirtualBackgroundButtonLabel }}
		</NcActionButton>
		<!-- Call layout switcher -->
		<NcActionButton v-if="isInCall"
			:close-after-click="true"
			@click="changeView">
			<template #icon>
				<GridView v-if="!isGrid"
					:size="20" />
				<PromotedView v-else
					:size="20" />
			</template>
			{{ changeViewText }}
		</NcActionButton>
		<NcActionSeparator />
		<NcActionButton :close-after-click="true"
			@click="showSettings">
			<template #icon>
				<Cog :size="20" />
			</template>
			{{ t('spreed', 'Devices settings') }}
		</NcActionButton>
	</NcActions>
</template>

<script>
import NcActions from '@nextcloud/vue/dist/Components/NcActions.js'
import NcActionSeparator from '@nextcloud/vue/dist/Components/NcActionSeparator.js'
import NcActionButton from '@nextcloud/vue/dist/Components/NcActionButton.js'
import { emit } from '@nextcloud/event-bus'
import PromotedView from '../missingMaterialDesignIcons/PromotedView.vue'
import Cog from 'vue-material-design-icons/Cog.vue'
import DotsHorizontal from 'vue-material-design-icons/DotsHorizontal.vue'
import GridView from '../missingMaterialDesignIcons/GridView.vue'
import HandBackLeft from 'vue-material-design-icons/HandBackLeft.vue'
import isInCall from '../../mixins/isInCall.js'
import Blur from 'vue-material-design-icons/Blur.vue'
import BlurOff from 'vue-material-design-icons/BlurOff.vue'

export default {
	name: 'TopBarMenu',

	components: {
		NcActions,
		NcActionSeparator,
		NcActionButton,
		PromotedView,
		Cog,
		DotsHorizontal,
		GridView,
		HandBackLeft,
		Blur,
		BlurOff,
	},

	mixins: [
		isInCall,
	],

	props: {
		/**
		 * The conversation token
		 */
		token: {
			type: String,
			required: true,
		},

		/**
		 * The local media model
		 */
		model: {
			type: Object,
			required: true,
		},

		showActions: {
			type: Boolean,
			default: true,
		},
	},

	data() {
		return {
			boundaryElement: document.querySelector('.main-view'),
		}
	},

	computed: {
		conversation() {
			return this.$store.getters.conversation(this.token) || this.$store.getters.dummyConversation
		},

		toggleVirtualBackgroundButtonLabel() {
			if (!this.isVirtualBackgroundEnabled) {
				return t('spreed', 'Blur background')
			}
			return t('spreed', 'Disable background blur')
		},

		container() {
			return this.$store.getters.getMainContainerSelector()
		},

		changeViewText() {
			if (this.isGrid) {
				return t('spreed', 'Speaker view')
			} else {
				return t('spreed', 'Grid view')
			}
		},

		isGrid() {
			return this.$store.getters.isGrid
		},

		isVirtualBackgroundAvailable() {
			return this.model.attributes.virtualBackgroundAvailable
		},

		isVirtualBackgroundEnabled() {
			return this.model.attributes.virtualBackgroundEnabled
		},

		raiseHandButtonLabel() {
			if (!this.model.attributes.raisedHand.state) {
				if (this.disableKeyboardShortcuts) {
					return t('spreed', 'Raise hand')
				}
				return t('spreed', 'Raise hand (R)')
			}
			if (this.disableKeyboardShortcuts) {
				return t('spreed', 'Lower hand')
			}
			return t('spreed', 'Lower hand (R)')
		},
	},

	methods: {
		toggleVirtualBackground() {
			if (this.model.attributes.virtualBackgroundEnabled) {
				this.model.disableVirtualBackground()
			} else {
				this.model.enableVirtualBackground()
			}
		},

		changeView() {
			this.$store.dispatch('setCallViewMode', { isGrid: !this.isGrid })
			this.$store.dispatch('selectedVideoPeerId', null)
		},

		showSettings() {
			emit('show-settings', {})
		},

		toggleHandRaised() {
			const state = !this.model.attributes.raisedHand?.state
			this.model.toggleHandRaised(state)
			this.$store.dispatch(
				'setParticipantHandRaised',
				{
					sessionId: this.$store.getters.getSessionId(),
					raisedHand: this.model.attributes.raisedHand,
				}
			)
		},
	},
}
</script>

<style lang="scss" scoped>

</style>
