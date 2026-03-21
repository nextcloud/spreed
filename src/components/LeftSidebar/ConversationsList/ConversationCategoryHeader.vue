<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcAppNavigationItem
		class="category-header"
		:name="name"
		allowCollapse
		:open="!collapsed"
		:forceMenu="isUserCategory"
		@update:open="toggleCollapsed">
		<!-- Invisible child to trigger the collapse chevron -->
		<li class="category-header__spacer" />
		<template #counter>
			<NcCounterBubble v-if="unreadCount > 0" :count="unreadCount" />
		</template>
		<template v-if="isUserCategory" #actions>
			<NcActionButton closeAfterClick @click="$emit('renameCategory', { categoryId, name })">
				<template #icon>
					<IconPencil :size="20" />
				</template>
				{{ t('spreed', 'Rename category') }}
			</NcActionButton>
			<NcActionButton closeAfterClick :disabled="isFirst" @click="$emit('moveCategoryUp', categoryId)">
				<template #icon>
					<IconArrowUp :size="20" />
				</template>
				{{ t('spreed', 'Move up') }}
			</NcActionButton>
			<NcActionButton closeAfterClick :disabled="isLast" @click="$emit('moveCategoryDown', categoryId)">
				<template #icon>
					<IconArrowDown :size="20" />
				</template>
				{{ t('spreed', 'Move down') }}
			</NcActionButton>
			<NcActionSeparator />
			<NcActionButton closeAfterClick class="critical" @click="$emit('deleteCategory', categoryId)">
				<template #icon>
					<IconDelete :size="20" />
				</template>
				{{ t('spreed', 'Delete category') }}
			</NcActionButton>
		</template>
	</NcAppNavigationItem>
</template>

<script>
import { t } from '@nextcloud/l10n'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcActionSeparator from '@nextcloud/vue/components/NcActionSeparator'
import NcAppNavigationItem from '@nextcloud/vue/components/NcAppNavigationItem'
import NcCounterBubble from '@nextcloud/vue/components/NcCounterBubble'
import IconArrowDown from 'vue-material-design-icons/ArrowDown.vue'
import IconArrowUp from 'vue-material-design-icons/ArrowUp.vue'
import IconDelete from 'vue-material-design-icons/Delete.vue'
import IconPencil from 'vue-material-design-icons/Pencil.vue'

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
		name: {
			type: String,
			required: true,
		},

		categoryId: {
			type: [Number, String],
			required: true,
		},

		collapsed: {
			type: Boolean,
			default: false,
		},

		unreadCount: {
			type: Number,
			default: 0,
		},

		isFirst: {
			type: Boolean,
			default: false,
		},

		isLast: {
			type: Boolean,
			default: false,
		},
	},

	emits: ['toggleCollapsed', 'renameCategory', 'moveCategoryUp', 'moveCategoryDown', 'deleteCategory'],

	computed: {
		isUserCategory() {
			return this.categoryId !== 'favorites' && this.categoryId !== 'other'
		},
	},

	methods: {
		t,

		toggleCollapsed() {
			this.$emit('toggleCollapsed', this.categoryId)
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
