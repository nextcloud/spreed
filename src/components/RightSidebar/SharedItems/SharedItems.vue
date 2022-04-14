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
	<div class="shared-items">
		<AppNavigationCaption :title="title" />
		<div class="files" :class="{'files__list' : isList}">
			<template v-for="file in itemsToDisplay">
				<FilePreview :key="file.id"
					:small-preview="isList"
					:row-layout="isList"
					:is-shared-items-tab="true"
					v-bind="file.messageParameters.file" />
			</template>
		</div>
		<Button v-if="hasMore"
			type="tertiary"
			class="shared-items__more"
			:wide="true"
			@click="handleCaptionClick">
			<template #icon>
				<DotsHorizontal :size="20"
					decorative
					title="" />
			</template>
			{{ buttonTitle }}
		</Button>
	</div>
</template>

<script>
import Button from '@nextcloud/vue/dist/Components/Button'
import FilePreview from '../../MessagesList/MessagesGroup/Message/MessagePart/FilePreview.vue'
import DotsHorizontal from 'vue-material-design-icons/DotsHorizontal.vue'
import AppNavigationCaption from '@nextcloud/vue/dist/Components/AppNavigationCaption'
import { showMessage } from '@nextcloud/dialogs'
import { SHARED_ITEM } from '../../../constants'

export default {
	name: 'SharedItems',

	components: {
		Button,
		FilePreview,
		DotsHorizontal,
		AppNavigationCaption,
	},

	props: {
		type: {
			type: String,
			required: true,
		},

		items: {
			type: Object,
			required: true,
		},
	},

	computed: {
		itemsToDisplay() {
			return Object.values(this.items).reverse().slice(0, 6)
		},

		title() {
			switch (this.type) {
			case SHARED_ITEM.TYPES.MEDIA:
				return t('spreed', 'Media')
			case SHARED_ITEM.TYPES.FILE:
				return t('spreed', 'Files')
			case SHARED_ITEM.TYPES.DECK_CARD:
				return t('spreed', 'Deck cards')
			case SHARED_ITEM.TYPES.VOICE:
				return t('spreed', 'Voice messages')
			case SHARED_ITEM.TYPES.LOCATION:
				return t('spreed', 'Locations')
			case SHARED_ITEM.TYPES.AUDIO:
				return t('spreed', 'Audio')
			case SHARED_ITEM.TYPES.OTHER:
			default:
				return t('spreed', 'Other')
			}
		},

		buttonTitle() {
			switch (this.type) {
			case SHARED_ITEM.TYPES.MEDIA:
				return t('spreed', 'Show all media')
			case SHARED_ITEM.TYPES.FILE:
				return t('spreed', 'Show all files')
			case SHARED_ITEM.TYPES.DECK_CARD:
				return t('spreed', 'Show all deck cards')
			case SHARED_ITEM.TYPES.VOICE:
				return t('spreed', 'Show all voice messages')
			case SHARED_ITEM.TYPES.LOCATION:
				return t('spreed', 'Show all locations')
			case SHARED_ITEM.TYPES.AUDIO:
				return t('spreed', 'Show all audio')
			case SHARED_ITEM.TYPES.OTHER:
			default:
				return t('spreed', 'Show all other')
			}
		},

		isList() {
			switch (this.type) {
			case SHARED_ITEM.TYPES.MEDIA:
				return false
			case SHARED_ITEM.TYPES.LOCATION:
				return false
			default:
				return true
			}
		},

		hasMore() {
			return Object.values(this.items).length > 6
		},
	},

	methods: {
		handleCaptionClick() {
			showMessage('Screenshot feature only. Implementation of the real feature will come soon! 😎')
			console.debug('Show more')
		},
	},
}
</script>

<style lang="scss" scoped>
.files {
	display: grid;
	grid-template-columns: 1fr 1fr 1fr;
	grid-template-rows: 1fr 1fr;
	grid-gap: 4px;
	&__list {
		display: flex;
		flex-direction: column;
	}

}

.shared-items {
	margin-bottom: 16px;
	&__more {
		margin-top: 8px;
	}
}
</style>
