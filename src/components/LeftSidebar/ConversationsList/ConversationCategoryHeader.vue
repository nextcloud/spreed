<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcAppNavigationItem
		class="category-header"
		:name="item.name"
		allowCollapse
		:open="!item.collapsed"
		:forceMenu="isUserCategory"
		@update:open="categoriesStore.toggleCollapsed(item.categoryId)">
		<!-- Invisible child to trigger the collapse chevron -->
		<li class="category-header__spacer" />
		<template #counter>
			<NcCounterBubble v-if="item.unreadCount > 0" :count="item.unreadCount" />
		</template>
		<template #actions>
			<template v-if="isUserCategory">
				<NcActionButton closeAfterClick @click="handleRenameCategory">
					<template #icon>
						<IconPencil :size="20" />
					</template>
					{{ t('spreed', 'Rename category') }}
				</NcActionButton>
			</template>
			<NcActionButton closeAfterClick :disabled="item.isFirst" @click="categoriesStore.moveCategoryUp(item.categoryId)">
				<template #icon>
					<IconArrowUp :size="20" />
				</template>
				{{ t('spreed', 'Move up') }}
			</NcActionButton>
			<NcActionButton closeAfterClick :disabled="item.isLast" @click="categoriesStore.moveCategoryDown(item.categoryId)">
				<template #icon>
					<IconArrowDown :size="20" />
				</template>
				{{ t('spreed', 'Move down') }}
			</NcActionButton>
			<template v-if="isUserCategory">
				<NcActionSeparator />
				<NcActionButton closeAfterClick class="critical" @click="handleDeleteCategory">
					<template #icon>
						<IconDelete :size="20" />
					</template>
					{{ t('spreed', 'Delete category') }}
				</NcActionButton>
			</template>
		</template>
	</NcAppNavigationItem>
</template>

<script>
import { t } from '@nextcloud/l10n'
import { spawnDialog } from '@nextcloud/vue/functions/dialog'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcActionSeparator from '@nextcloud/vue/components/NcActionSeparator'
import NcAppNavigationItem from '@nextcloud/vue/components/NcAppNavigationItem'
import NcCounterBubble from '@nextcloud/vue/components/NcCounterBubble'
import IconArrowDown from 'vue-material-design-icons/ArrowDown.vue'
import IconArrowUp from 'vue-material-design-icons/ArrowUp.vue'
import IconDelete from 'vue-material-design-icons/Delete.vue'
import IconPencil from 'vue-material-design-icons/Pencil.vue'
import ConfirmDialog from '../../UIShared/ConfirmDialog.vue'
import { useConversationCategoriesStore } from '../../../stores/conversationCategories.ts'

export default {
	name: 'ConversationCategoryHeader',

	components: {
		NcActionButton,
		NcActionSeparator,
		NcAppNavigationItem,
		NcCounterBubble,
		IconArrowDown,
		IconArrowUp,
		IconDelete,
		IconPencil,
	},

	props: {
		item: {
			type: Object,
			required: true,
		},
	},

	setup() {
		return {
			categoriesStore: useConversationCategoriesStore(),
		}
	},

	computed: {
		isUserCategory() {
			return this.item.categoryId !== 'favorites' && this.item.categoryId !== 'other'
		},
	},

	methods: {
		t,

		async handleRenameCategory() {
			const name = await spawnDialog(ConfirmDialog, {
				name: t('spreed', 'Rename category'),
				isForm: true,
				inputProps: { label: t('spreed', 'Category name'), value: this.item.name },
				buttons: [
					{ label: t('spreed', 'Cancel'), variant: 'tertiary', callback: () => false },
					{ label: t('spreed', 'Save'), variant: 'primary', type: 'submit', callback: () => true },
				],
			})
			if (!name || name === this.item.name) {
				return
			}
			await this.categoriesStore.updateCategoryName(this.item.categoryId, name)
		},

		async handleDeleteCategory() {
			const confirmed = await spawnDialog(ConfirmDialog, {
				name: t('spreed', 'Delete category'),
				message: t('spreed', 'Do you really want to delete "{name}"? Conversations in this category will be moved to "Other".', { name: this.item.name }),
				buttons: [
					{ label: t('spreed', 'Cancel'), variant: 'tertiary', callback: () => undefined },
					{ label: t('spreed', 'Delete'), variant: 'error', callback: () => true },
				],
			})

			if (!confirmed) {
				return
			}

			await this.categoriesStore.removeCategory(this.item.categoryId)
			// Remove the deleted category ID from all conversations in the Vuex store
			const categoryIdStr = String(this.item.categoryId)
			const conversations = this.$store.getters.conversationsList
			for (const conversation of conversations) {
				if (conversation.categoryIds?.includes(categoryIdStr)) {
					conversation.categoryIds = conversation.categoryIds.filter((id) => id !== categoryIdStr)
				}
			}
		},
	},
}
</script>

<style lang="scss" scoped>
.category-header {
	// Hide the empty icon slot and add padding so the category name aligns with conversation avatars
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

:deep(.critical .action-button) {
	color: var(--color-error) !important;
}
</style>
