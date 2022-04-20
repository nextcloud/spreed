<!--
  - @copyright Copyright (c) 2022 Marco Ambrosini <marcoambrosini@pm.me>
  -
  - @author Marco Ambrosini <marcoambrosini@pm.me>
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
	<Modal size="large" v-on="$listeners">
		<div class="shared-items-browser">
			<div class="shared-items-browser__navigation">
				<template v-for="type in sharedItemsOrder">
					<Button v-if="sharedItems[type]"
						:key="type"
						:class="{'active' : activeTab === type}"
						type="tertiary"
						@click="handleTabClick(type)">
						{{ getTitle(type) }}
					</Button>
				</template>
			</div>
			<div class="shared-items-browser__content">
				<SharedItems :type="activeTab"
					:items="sharedItems[activeTab]" />
			</div>
		</div>
	</Modal>
</template>

<script>
import Modal from '@nextcloud/vue/dist/Components/Modal'
import Button from '@nextcloud/vue/dist/Components/Button'
import SharedItems from '../SharedItems.vue'
import sharedItems from '../../../../mixins/sharedItems'

export default {
	name: 'SharedItemsBrowser',

	components: {
		Modal,
		Button,
		SharedItems,
	},

	mixins: [sharedItems],

	props: {
		sharedItems: {
			type: Object,
			required: true,
		},

		activeTab: {
			type: String,
			required: true,
		},
	},

	methods: {
		handleTabClick(type) {
			this.$emit('update:active-tab', type)
		},
	},
}
</script>

<style lang="scss" scoped>
.shared-items-browser {
	width: 100%;
	height: 100%;
	position:relative;
	display: flex;
	flex-direction: column;
	&__navigation {
		display: flex;
		gap: 8px;
		padding: 16px;
		flex-wrap: wrap;
		justify-content: center;
	}
	&__content {
		overflow-y: auto;
		overflow-x: hidden;
		margin: 0 12px;
	}
}

::v-deep .button-vue {
	border-radius: var(--border-radius-large);
	&.active {
		background-color: var(--color-primary-light-hover);
	}
}
</style>
