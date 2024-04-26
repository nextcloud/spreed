<!--
  - SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="button-holder">
		<NcButton type="tertiary-on-primary"
			:aria-label="ariaLabel"
			@click="$emit('click')">
			<template #icon>
				<MenuPeople :size="20" />
			</template>
		</NcButton>
	</div>
</template>

<script>
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'

import MenuPeople from './assets/missingMaterialDesignIcons/MenuPeople.vue'

export default {

	name: 'PublicShareSidebarTrigger',

	components: {
		NcButton,
		MenuPeople,
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
			if (this.sidebarState.isOpen) {
				return t('spreed', 'Close Talk sidebar')
			}

			return t('spreed', 'Open Talk sidebar')
		},
	},

}
</script>

<style scoped>
.button-holder {
	margin: 2px 5px 2px 2px;
	display: flex;
	justify-content: center;
	height: 44px !important;
}
</style>
