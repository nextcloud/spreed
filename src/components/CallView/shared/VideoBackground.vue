<!--
  - SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script setup lang="ts">
import { useIsDarkTheme } from '@nextcloud/vue/composables/useIsDarkTheme'

const isDarkTheme = useIsDarkTheme()
</script>

<template>
	<div class="video-background" :class="{ 'dark-theme': isDarkTheme }" />
</template>

<style lang="scss" scoped>

.video-background {
	position: absolute;
	inset-inline-start: 0;
	top: 0;
	height: 100%;
	width: 100%;
	background-color: rgba(var(--overlay-color), 0.3);
	background-image: none;

	--overlay-color: 0, 0, 0;
	&.dark-theme {
		--overlay-color: 255, 255, 255;
	}
}
</style>
