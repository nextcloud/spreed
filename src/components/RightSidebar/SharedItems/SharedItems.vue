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
			<template v-for="file in filesToDisplay">
				<FilePreview :key="file.id"
					:small-preview="isList"
					:row-layout="isList"
					:is-shared-items-tab="true"
					v-bind="file.messageParameters.file" />
			</template>
		</div>
		<Button type="tertiary"
			class="shared-items__more"
			:wide="true"
			@click="handleCaptionClick">
			<template #icon>
				<DotsHorizontal :size="20"
					decorative
					title="" />
			</template>
			Show all {{ title }}
		</Button>
	</div>
</template>

<script>
import Button from '@nextcloud/vue/dist/Components/Button'
import FilePreview from '../../MessagesList/MessagesGroup/Message/MessagePart/FilePreview.vue'
import DotsHorizontal from 'vue-material-design-icons/DotsHorizontal.vue'
import AppNavigationCaption from '@nextcloud/vue/dist/Components/AppNavigationCaption'

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
		filesToDisplay() {
			return Object.values(this.items).slice(0, 5)
		},

		title() {
			switch (this.type) {
			case 'media':
				return t('spreed', 'Media')
			case 'file':
				return t('spreed', 'Files')
			case 'deck-card':
				return t('spreed', 'Deck cards')
			case 'voice':
				return t('spreed', 'Voice messages')
			case 'location':
				return t('spreed', 'Locations')
			case 'audio':
				return t('spreed', 'Audio')
			case 'other':
				return t('spreed', 'Other')
			default:
				return ''
			}
		},

		isList() {
			switch (this.type) {
			case 'file':
				return true
			case 'voice':
				return true
			case 'audio':
				return true
			case 'other':
				return true
			default:
				return false
			}
		},
	},

	methods: {
		handleCaptionClick() {
			console.debug('Show more')
		},
	},
}
</script>

<style lang="scss" scoped>
.files {
	display: grid;
	grid-template-columns: 1fr 1fr 1fr;
	&__list {
		display: flex;
		flex-direction: column;
	}
}

.shared-items {
	margin-bottom: 16px;
	&__more {
		margin-top: 4px;
	}
}
</style>
