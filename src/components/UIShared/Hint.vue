<!--
  - SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<li class="app-navigation-hint">
		{{ hint }}
	</li>
</template>

<script>
export default {
	name: 'Hint',
	props: {
		hint: {
			type: String,
			required: true,
		},
	},
}
</script>

<style lang="scss" scoped>
	.app-navigation-hint {
		color: var(--color-text-maxcontrast);
		list-style-type: none;
		white-space: nowrap;
		text-overflow: ellipsis;
		box-shadow: none !important;
		user-select: none;
		pointer-events: none;
		padding-left: 10px;
	}
</style>
