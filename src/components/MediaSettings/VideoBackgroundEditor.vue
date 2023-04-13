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
		<button class="background-editor__element"
			@click="clearBackground">
			<Cancel :size="20" />
			{{ t('spreed', 'clear') }}
		</button>
		<button :disabled="!blurPreviewAvailable"
			class="background-editor__element"
			@click="blurBackground">
			<Blur :size="20" />
			{{ t('spreed', 'blur') }}
		</button>
		<button class="background-editor__element">
			<ImagePlus :size="20" />
			{{ t('spreed', 'upload') }}
		</button>
	</div>
</template>

<script>
import Blur from 'vue-material-design-icons/Blur.vue'
import Cancel from 'vue-material-design-icons/Cancel.vue'
import ImagePlus from 'vue-material-design-icons/ImagePlus.vue'

export default {
	name: 'VideoBackgroundEditor',

	components: {
		Cancel,
		Blur,
		ImagePlus,
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

	computed: {
		blurPreviewAvailable() {
			return this.virtualBackground.isAvailable()
		},
	},

	methods: {
		clearBackground() {
			this.$emit('clear')
		},

		blurBackground() {
			this.$emit('blur')
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
		align-content: center;
	 }
}
</style>
