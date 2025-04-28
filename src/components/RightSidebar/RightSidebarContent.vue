<!--
  - SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script setup lang="ts">
import { computed } from 'vue'

import IconArrowLeft from 'vue-material-design-icons/ArrowLeft.vue'
import IconMagnify from 'vue-material-design-icons/Magnify.vue'

import { t } from '@nextcloud/l10n'

import NcAppSidebarHeader from '@nextcloud/vue/components/NcAppSidebarHeader'
import NcButton from '@nextcloud/vue/components/NcButton'

import type {
	Conversation,
} from '../../types/index.ts'

const props = defineProps<{
	isUser: boolean,
	conversation: Conversation,
	state: 'default' | 'search',
	mode: 'compact' | 'preview' | 'full',
}>()

const emit = defineEmits<{
	(event: 'update:search', value: boolean): void
}>()

const sidebarTitle = computed(() => {
	if (props.state === 'search') {
		return t('spreed', 'Search in {name}', { name: props.conversation.displayName }, {
			escape: false,
			sanitize: false,
		})
	}
	return props.conversation.displayName
})
</script>

<template>
	<div class="content">
		<template v-if="state === 'default'">
			<div v-if="isUser" class="content__actions">
				<NcButton type="tertiary"
					:title="t('spreed', 'Search messages')"
					:aria-label="t('spreed', 'Search messages')"
					@click="emit('update:search', true)">
					<template #icon>
						<IconMagnify :size="20" />
					</template>
				</NcButton>
			</div>

			<div class="content__header">
				<NcAppSidebarHeader class="content__name content__name--has-actions"
					:name="sidebarTitle"
					:title="sidebarTitle" />
			</div>
		</template>

		<!-- Search messages in this conversation -->
		<template v-else-if="isUser && state === 'search'">
			<div class="content__header content__header--row">
				<NcButton type="tertiary"
					:title="t('spreed', 'Back')"
					:aria-label="t('spreed', 'Back')"
					@click="emit('update:search', false)">
					<template #icon>
						<IconArrowLeft class="bidirectional-icon" :size="20" />
					</template>
				</NcButton>

				<NcAppSidebarHeader class="content__name"
					:name="sidebarTitle"
					:title="sidebarTitle" />
			</div>
		</template>
	</div>
</template>

<style lang="scss" scoped>
.content {
	&__header {
		flex-grow: 1;
		display: flex;
		flex-direction: column;
		align-items: start;
		gap: var(--default-grid-baseline);
		padding-block: calc(2 * var(--default-grid-baseline)) var(--default-grid-baseline);
		padding-inline-start: calc(2 * var(--default-grid-baseline));

		&--row {
			flex-direction: row;
			align-items: center;
		}

		.content__name {
			--actions-offset: calc(var(--default-grid-baseline) + var(--default-clickable-area));
			width: 100%;
			margin: 0;
			padding-inline-end: var(--app-sidebar-close-button-offset);
			font-size: 20px;
			line-height: var(--default-clickable-area);
			overflow: hidden;
			white-space: nowrap;
			text-overflow: ellipsis;

			&--has-actions {
				padding-inline-end: calc(var(--actions-offset) + var(--app-sidebar-close-button-offset));
			}
		}
	}

	&__actions {
		position: absolute !important;
		z-index: 2;
		top: calc(2 * var(--default-grid-baseline));
		inset-inline-end: calc(var(--default-grid-baseline) + var(--app-sidebar-close-button-offset));
		display: flex;
		gap: var(--default-grid-baseline);
	}
}
</style>
