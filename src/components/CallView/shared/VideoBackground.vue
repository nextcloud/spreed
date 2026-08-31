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
	// A solid surface: nothing of the call view behind the tile shows through
	// it. The two colours are what the translucent tint they replace used to
	// composite to over the background of the call, so a tile keeps the shade
	// it had in either theme.
	background-color: #363636;

	&.dark-theme {
		background-color: #515151;
	}
}
</style>
