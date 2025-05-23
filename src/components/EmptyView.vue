<!--
  - SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcEmptyContent class="empty-view" :name="name" :description="description">
		<template #icon>
			<slot name="icon" />
		</template>
	</NcEmptyContent>
</template>

<script>
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'

export default {
	name: 'EmptyView',

	components: {
		NcEmptyContent,
	},

	props: {
		name: {
			type: String,
			required: true,
		},

		description: {
			type: String,
			required: true,
		},
	},
}
</script>

<style lang="scss" scoped>
.empty-view {
	height: 100%;
	padding: calc(var(--default-grid-baseline) * 4);
}
</style>
