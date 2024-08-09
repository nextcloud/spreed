<!--
  - SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
  -->

<script setup>
import { computed } from 'vue'

import IconAccountMultiple from 'vue-material-design-icons/AccountMultiple.vue'

import { emit } from '@nextcloud/event-bus'
import { n } from '@nextcloud/l10n'

import NcActionButton from '@nextcloud/vue/dist/Components/NcActionButton.js'
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'

import { useStore } from '../../composables/useStore.js'
import BrowserStorage from '../../services/BrowserStorage.js'

const props = defineProps({
	token: {
		type: String,
		required: true,
	},
	inMenu: {
		type: Boolean,
		default: false,
	},
})

const store = useStore()

const ButtonComponent = computed(() => props.inMenu ? NcActionButton : NcButton)

const participantsInCall = computed(() => store.getters.participantsInCall(props.token))

const participantsInCallLabel = computed(() =>
	n('spreed', '%n participant in call', '%n participants in call', participantsInCall.value),
)

const text = computed(() => props.inMenu ? participantsInCallLabel.value : participantsInCall.value || '')

/**
 * Open the sidebar
 * @param {string} [activeTab] - tab id to open
 */
function openSidebar(activeTab) {
	if (typeof activeTab === 'string') {
		emit('spreed:select-active-sidebar-tab', activeTab)
	}
	store.dispatch('showSidebar')
	BrowserStorage.setItem('sidebarOpen', 'true')
}
</script>

<template>
	<ButtonComponent :title="participantsInCallLabel"
		:aria-label="participantsInCallLabel"
		type="tertiary"
		@click="openSidebar('participants')">
		<template #icon>
			<IconAccountMultiple :size="20" />
		</template>
		{{ text }}
	</ButtonComponent>
</template>

<style scoped lang="scss">

</style>
