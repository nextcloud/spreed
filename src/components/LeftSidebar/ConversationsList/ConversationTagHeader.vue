<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script setup lang="ts">
import type { Conversation, ConversationTag } from '../../../types/index.ts'

import { t } from '@nextcloud/l10n'
import { spawnDialog } from '@nextcloud/vue/functions/dialog'
import { computed } from 'vue'
import { useStore } from 'vuex'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcActionSeparator from '@nextcloud/vue/components/NcActionSeparator'
import NcAppNavigationItem from '@nextcloud/vue/components/NcAppNavigationItem'
import NcCounterBubble from '@nextcloud/vue/components/NcCounterBubble'
import IconArrowDown from 'vue-material-design-icons/ArrowDown.vue'
import IconArrowUp from 'vue-material-design-icons/ArrowUp.vue'
import IconPencilOutline from 'vue-material-design-icons/PencilOutline.vue'
import IconTrashCanOutline from 'vue-material-design-icons/TrashCanOutline.vue'
import ConfirmDialog from '../../UIShared/ConfirmDialog.vue'
import { useConversationTagsStore } from '../../../stores/conversationTags.ts'

export type TagHeaderItem = ConversationTag & {
	_type: 'tag-header'
	unreadCount: number
	isFirst?: boolean
	isLast?: boolean
}

const props = defineProps<{
	item: TagHeaderItem
}>()

const vuexStore = useStore()
const tagsStore = useConversationTagsStore()

const isCustomTag = computed(() => props.item.type === 'custom')

/**
 * Assign a new name to the tag via dialog
 */
async function handleRenameTag() {
	const name = await spawnDialog(ConfirmDialog, {
		name: t('spreed', 'Rename tag'),
		isForm: true,
		inputProps: { label: t('spreed', 'Tag name'), value: props.item.name },
		buttons: [
			{ label: t('spreed', 'Cancel'), variant: 'tertiary', callback: () => false },
			{ label: t('spreed', 'Save'), variant: 'primary', type: 'submit', callback: () => true },
		],
	})
	if (!name || typeof name !== 'string' || name === props.item.name) {
		return
	}
	await tagsStore.updateTagName(props.item.id, name)
}

/**
 * Delete a custom tag and unassign from all conversations
 */
async function handleDeleteTag() {
	const confirmed = await spawnDialog(ConfirmDialog, {
		name: t('spreed', 'Delete tag'),
		message: t('spreed', 'Do you really want to delete "{name}"? Conversations with this tag will be moved to "Other".', { name: props.item.name }),
		buttons: [
			{ label: t('spreed', 'Cancel'), variant: 'tertiary', callback: () => undefined },
			{ label: t('spreed', 'Delete'), variant: 'error', callback: () => true },
		],
	})

	if (!confirmed) {
		return
	}

	await tagsStore.removeTag(props.item.id)
	// Remove the deleted tag ID from all conversations in the Vuex store
	const conversations = vuexStore.getters.conversationsList as Conversation[]
	for (const conversation of conversations) {
		if (conversation.tagIds?.includes(props.item.id)) {
			vuexStore.dispatch('setConversationProperties', {
				token: conversation.token,
				properties: { tagIds: conversation.tagIds.filter((id: string) => id !== props.item.id) },
			})
		}
	}
}
</script>

<template>
	<NcAppNavigationItem
		class="tag-header"
		:name="item.name"
		allowCollapse
		:open="!item.collapsed"
		:forceMenu="isCustomTag"
		@update:open="tagsStore.toggleCollapsed(item.id)">
		<!-- Invisible child to trigger the collapse chevron -->
		<li class="tag-header__spacer" />
		<template #counter>
			<NcCounterBubble v-if="item.unreadCount > 0" :count="item.unreadCount" />
		</template>
		<template #actions>
			<template v-if="isCustomTag">
				<NcActionButton closeAfterClick @click="handleRenameTag">
					<template #icon>
						<IconPencilOutline :size="20" />
					</template>
					{{ t('spreed', 'Rename tag') }}
				</NcActionButton>
			</template>
			<NcActionButton closeAfterClick :disabled="item.isFirst" @click="tagsStore.moveTag(item.id, -1)">
				<template #icon>
					<IconArrowUp :size="20" />
				</template>
				{{ t('spreed', 'Move up') }}
			</NcActionButton>
			<NcActionButton closeAfterClick :disabled="item.isLast" @click="tagsStore.moveTag(item.id, 1)">
				<template #icon>
					<IconArrowDown :size="20" />
				</template>
				{{ t('spreed', 'Move down') }}
			</NcActionButton>
			<template v-if="isCustomTag">
				<NcActionSeparator />
				<NcActionButton
					closeAfterClick
					class="critical"
					@click="handleDeleteTag">
					<template #icon>
						<IconTrashCanOutline :size="20" />
					</template>
					{{ t('spreed', 'Delete tag') }}
				</NcActionButton>
			</template>
		</template>
	</NcAppNavigationItem>
</template>

<style lang="scss" scoped>
.tag-header {
	// Hide the empty icon slot and add padding so the tag name aligns with conversation avatars
	:deep(.app-navigation-entry-icon) {
		display: none !important;
	}

	:deep(.app-navigation-entry__name) {
		font-weight: bold;
		// Compensate for hidden icon: align with avatar start position
		padding-inline-start: calc(var(--default-grid-baseline, 4px) * 3) !important;
	}

	&__spacer {
		display: none;
	}
}

.critical > :deep(.action-button) {
	color: var(--color-text-error);
}
</style>
