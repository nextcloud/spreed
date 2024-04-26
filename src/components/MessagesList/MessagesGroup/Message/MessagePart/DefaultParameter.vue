<!--
  - SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<a v-if="link"
		:href="link"
		class="external"
		target="_blank"
		rel="noopener noreferrer">
		<strong>{{ name }}</strong>
	</a>
	<strong v-else>{{ name }}</strong>
</template>

<script>
export default {
	name: 'DefaultParameter',
	props: {
		type: {
			type: String,
			required: true,
		},
		id: {
			type: String,
			required: true,
		},
		name: {
			type: String,
			required: true,
		},
		link: {
			type: String,
			default: '',
		},
	},
}
</script>

<style lang="scss" scoped>
.external:after {
	content: ' ↗';
}
</style>
