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
	<Modal size="large">
		<div class="shared-items-browser__navigation">
			<template v-for="type in sharedItemsOrder">
				<Button v-if="sharedItems[type]"
					:key="type"
					type="tertiary"
					@click="handleTabClick">
					{{ type }}
				</Button>
			</template>
		</div>
	</Modal>
</template>

<script>
import Modal from '@nextcloud/vue/dist/Components/Modal'
import Button from '@nextcloud/vue/dist/Components/Button'

export default {
	name: 'SharedItemsBrowser',

	components: {
		Modal,
		Button,
	},

	props: {
		sharedItems: {
			type: Object,
			required: true,
		},

		sharedItemsOrder: {
			type: Array,
			required: true,
		},

		// The tab that is open upon opening the modal
		initialTab: {
			type: String,
			default: 'media',
		},
	},

	data() {
		return {
			activeTab: this.initialTab,
		}
	},

	methods: {
		handleTabClick() {
			console.debug('Tab click')
		},
	},
}
</script>

<style lang="scss" scoped>
.shared-items-browser__navigation {
	display: flex;
	gap: 8px;
	padding: 16px;
	flex-wrap: wrap;
	justify-content: center;
}
</style>
