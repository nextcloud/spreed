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
		<Button type="tertiary"
			:wide="true"
			@click="handleCaptionClick">
			{{ title }}
		</Button>
		<div class="files">
			<template v-for="item in items">
				<FilePreview :key="item.id"
					v-bind="item.messageParameters.file" />
			</template>
		</div>
	</div>
</template>

<script>
import Button from '@nextcloud/vue/dist/Components/Button'
import FilePreview from '../../MessagesList/MessagesGroup/Message/MessagePart/FilePreview.vue'

export default {
	name: 'SharedItems',

	components: {
		Button,
		FilePreview,
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
				return t('spreed', 'Music')
			case 'other':
				return t('spreed', 'Other')
			default:
				return ''
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
.shared-items {
	margin-bottom: 8px;
}

::v-deep .button-vue--vue-tertiary {
	justify-content: flex-start;
	border-radius: var(--border-radius-large);
	opacity: 1;
}
</style>
