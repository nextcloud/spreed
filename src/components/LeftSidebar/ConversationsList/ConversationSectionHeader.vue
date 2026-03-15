<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<li
		class="section-header"
		:aria-label="collapsed ? t('spreed', 'Expand section {name}', { name }) : t('spreed', 'Collapse section {name}', { name })"
		role="button"
		tabindex="0"
		@click="toggleCollapsed"
		@keydown.enter="toggleCollapsed"
		@keydown.space.prevent="toggleCollapsed">
		<span class="section-header__name">{{ name }}</span>
		<NcCounterBubble v-if="unreadCount > 0" class="section-header__counter">
			{{ unreadCount }}
		</NcCounterBubble>
		<div class="section-header__chevron">
			<IconChevronUp v-if="!collapsed" :size="20" />
			<IconChevronDown v-else :size="20" />
		</div>
	</li>
</template>

<script>
import { t } from '@nextcloud/l10n'
import NcCounterBubble from '@nextcloud/vue/components/NcCounterBubble'
import IconChevronDown from 'vue-material-design-icons/ChevronDown.vue'
import IconChevronUp from 'vue-material-design-icons/ChevronUp.vue'

export default {
	name: 'ConversationSectionHeader',

	components: {
		NcCounterBubble,
		IconChevronDown,
		IconChevronUp,
	},

	props: {
		name: {
			type: String,
			required: true,
		},

		sectionId: {
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
	},

	emits: ['toggleCollapsed'],

	methods: {
		t,

		toggleCollapsed() {
			this.$emit('toggleCollapsed', this.sectionId)
		},
	},
}
</script>

<style lang="scss" scoped>
.section-header {
	display: flex;
	justify-content: space-between;
	align-items: flex-end;
	width: 100%;
	height: var(--default-clickable-area, 44px);
	box-sizing: border-box;
	cursor: pointer;
	user-select: none;
	padding-inline: calc(var(--default-grid-baseline, 4px) * 2);

	&:hover {
		background-color: var(--color-background-hover);
		border-radius: var(--border-radius-element, var(--border-radius-large));
	}

	&__name {
		font-weight: bold;
		color: var(--color-main-text);
		font-size: var(--default-font-size);
		line-height: 28px;
		white-space: nowrap;
		overflow: hidden;
		text-overflow: ellipsis;
	}

	&__counter {
		flex-shrink: 0;
		margin-inline: var(--default-grid-baseline, 4px);
		margin-bottom: 2px;
	}

	&__chevron {
		flex: 0 0 28px;
		display: flex;
		align-items: center;
		justify-content: center;
		height: 28px;
		color: var(--color-text-maxcontrast);
	}
}
</style>
