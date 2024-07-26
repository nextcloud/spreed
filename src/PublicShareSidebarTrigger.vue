<!--
  - SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="button-wrapper">
		<NcButton type="tertiary-on-primary"
			:title="ariaLabel"
			:aria-label="ariaLabel"
			@click="$emit('click')">
			<template #icon>
				<MessageText fill-color="var(--color-background-plain-text)" :size="20" />
			</template>
		</NcButton>
	</div>
</template>

<script>
import MessageText from 'vue-material-design-icons/MessageText.vue'

import { t } from '@nextcloud/l10n'

import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'

export default {
	name: 'PublicShareSidebarTrigger',

	components: {
		MessageText,
		NcButton,
	},

	props: {
		sidebarState: {
			type: Object,
			required: true,
		},
	},

	emits: ['click'],

	computed: {
		ariaLabel() {
			return this.sidebarState.isOpen
				? t('spreed', 'Close Talk sidebar')
				: t('spreed', 'Open Talk sidebar')
		},
	},

	methods: {
		t,
	},
}
</script>

<style scoped>
.button-wrapper {
	height: var(--header-height) !important;
	width: var(--default-clickable-area) !important;
	display: flex;
	justify-content: center;
	align-items: center;
}
</style>
