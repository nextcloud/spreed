<!--
  - SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="video-background" :style="{'background-color': backgroundColor }" />
</template>

<script>
import usernameToColor from '@nextcloud/vue/functions/usernameToColor'

export default {
	name: 'VideoBackground',

	props: {
		displayName: {
			type: String,
			default: null,
		},
		user: {
			type: String,
			default: '',
		},
	},

	computed: {
		backgroundColor() {
			// If the prop is empty. We're not checking for the default value
			// because the user's displayName might be '?'
			if (!this.displayName) {
				return 'var(--color-text-maxcontrast)'
			} else {
				const color = usernameToColor(this.displayName)
				return `rgb(${color.r}, ${color.g}, ${color.b})`
			}
		},
	},
}
</script>

<style lang="scss" scoped>
.video-background {
	position: absolute;
	inset-inline-start: 0;
	top: 0;
	height: 100%;
	width: 100%;

	&::after {
		content: ' ';
		background-color: rgba(0, 0, 0, 0.12);
		position: absolute;
		width: 100%;
		height: 100%;
		top: 0;
		inset-inline-start: 0;
	}
}
</style>
