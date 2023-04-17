<!--
  - @copyright Copyright (c) 2023 Marco Ambrosini <marcoambrosini@icloud.com>
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
	<div class="background-editor">
		<button key="clear"
			class="background-editor__element"
			:class="{'background-editor__element--selected': selectedBackground === 'clear'}"
			@click="handleSelectBackground('clear')">
			<Cancel :size="20" />
			{{ t('spreed', 'clear') }}
		</button>
		<button key="blur"
			:disabled="!blurPreviewAvailable"
			class="background-editor__element"
			:class="{'background-editor__element--selected': selectedBackground === 'blur'}"
			@click="handleSelectBackground('blur')">
			<Blur :size="20" />
			{{ t('spreed', 'blur') }}
		</button>
		<button key="upload" class="background-editor__element">
			<ImagePlus :size="20" />
			{{ t('spreed', 'upload') }}
		</button>
		<button v-for="path in backgrounds"
			:key="path"
			class="background-editor__element"
			:class="{'background-editor__element--selected': selectedBackground === path}"
			:style="{
				'background-image': 'url(' + path + ')'
			}"
			@click="handleSelectBackground(path)">
			<CheckBold v-if="selectedBackground === path"
				:size="40"
				fill-color="#fff" />
		</button>
	</div>
</template>

<script>
import Blur from 'vue-material-design-icons/Blur.vue'
import Cancel from 'vue-material-design-icons/Cancel.vue'
import CheckBold from 'vue-material-design-icons/CheckBold.vue'
import ImagePlus from 'vue-material-design-icons/ImagePlus.vue'

import { imagePath } from '@nextcloud/router'

export default {
	name: 'VideoBackgroundEditor',

	components: {
		Cancel,
		Blur,
		ImagePlus,
		CheckBold,
	},

	props: {
		token: {
			type: String,
			required: true,
		},

		virtualBackground: {
			type: Object,
			required: true,
		},
	},

	data() {
		return {
			selectedBackground: undefined,
		}
	},

	computed: {
		blurPreviewAvailable() {
			return this.virtualBackground.isAvailable()
		},

		backgrounds() {
			return [
				imagePath('spreed', 'backgrounds/1.jpg'),
				imagePath('spreed', 'backgrounds/2.jpg'),
				imagePath('spreed', 'backgrounds/3.jpg'),
				imagePath('spreed', 'backgrounds/4.jpg'),
				imagePath('spreed', 'backgrounds/5.jpg'),
				imagePath('spreed', 'backgrounds/6.jpg'),
			]
		},
	},

	methods: {
		handleSelectBackground(background) {
			this.$emit('update-background', background)
			this.selectedBackground = background
		},
	},
}
</script>

<style scoped lang="scss">
.background-editor {
	display: grid;
	grid-template-columns: 1fr 1fr 1fr;
	gap: calc(var(--default-grid-baseline) * 2);
	margin-top: calc(var(--default-grid-baseline) * 2);

	&__element {
		border: none;
		margin: 0;
		border-radius: calc(var(--border-radius-large)* 1.5);
		background: #1cafff2e;
		height: calc(var(--default-grid-baseline) * 16);
		display: flex;
		flex-direction: column;
		justify-content: center;
		align-items: center;
		background-size: cover;

		&--selected {
			box-shadow: inset 0 0 calc(var(--default-grid-baseline) * 4) var(--color-main-background);
		}
	 }
}
</style>
