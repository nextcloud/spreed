<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script setup lang="ts">
import { ref } from 'vue'
import DefaultParameter from './DefaultParameter.vue'

defineProps<{
	type: string
	id: string
	name: string
	link: string
	thumb: string
}>()

const failed = ref(false)
</script>

<template>
	<DefaultParameter
		v-if="failed"
		:id
		:type
		:name
		:link />
	<a
		v-else
		:href="link"
		class="image-preview"
		target="_blank"
		rel="noopener noreferrer"
		:title="name">
		<img
			:src="thumb"
			:alt="name"
			class="image-preview__image"
			loading="lazy"
			@error="failed = true">
	</a>
</template>

<style lang="scss" scoped>
.image-preview {
	display: inline-block;
	margin: 4px;

	&__image {
		display: block;
		max-width: min(100%, 320px);
		max-height: 320px;
		border-radius: var(--border-radius-large);
	}
}
</style>
