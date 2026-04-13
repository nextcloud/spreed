<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script setup lang="ts">
import { t } from '@nextcloud/l10n'
import NcAvatar from '@nextcloud/vue/components/NcAvatar'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import IconOpenInNew from 'vue-material-design-icons/OpenInNew.vue'
import IconPhoneOutline from 'vue-material-design-icons/PhoneOutline.vue'
import LocalTime from '../UIShared/LocalTime.vue'
import { AVATAR } from '../../constants.ts'

defineProps<{
	userId: string
	displayName?: string
	role?: string
	organisation?: string
	timezone?: string
}>()

const emit = defineEmits<{
	close: [value?: 'navigate' | 'integration']
}>()

/**
 * Navigate to the Talk app to start the call
 */
function navigate() {
	emit('close', 'navigate')
}

/**
 * Start the call immediately in a floating window
 */
function startCall() {
	emit('close', 'integration')
}
</script>

<template>
	<NcDialog
		class="floating-call-dialog"
		:name="t('spreed', 'Direct call')"
		size="small"
		@closing="emit('close')">
		<div class="floating-call-dialog__info">
			<NcAvatar
				class="floating-call-dialog__avatar"
				:user="userId"
				:displayName="displayName ?? userId"
				:size="AVATAR.SIZE.MEDIUM"
				disableMenu />
			<div class="floating-call-dialog__text">
				<p class="floating-call-dialog__name">
					{{ displayName ?? userId }}
				</p>
				<p v-if="role || organisation" class="floating-call-dialog__meta">
					<span v-if="role">{{ role }}</span>
					<span v-if="role && organisation" class="floating-call-dialog__meta-separator" aria-hidden="true">·</span>
					<span v-if="organisation">{{ organisation }}</span>
				</p>
				<LocalTime v-if="timezone" class="floating-call-dialog__local-time" :timezone="timezone" />
			</div>
		</div>
		<template #actions>
			<NcButton
				wide
				alignment="center"
				@click="navigate">
				<template #icon>
					<IconOpenInNew :size="20" />
				</template>
				{{ t('spreed', 'Continue in Talk app') }}
			</NcButton>
			<NcButton
				wide
				alignment="center"
				variant="primary"
				@click="startCall">
				<template #icon>
					<IconPhoneOutline :size="20" />
				</template>
				{{ t('spreed', 'Call right now!') }}
			</NcButton>
		</template>
	</NcDialog>
</template>

<style scoped lang="scss">
.floating-call-dialog__info {
	display: flex;
	align-items: center;
	gap: calc(var(--default-grid-baseline) * 3);
	padding-block-end: calc(var(--default-grid-baseline) * 4);
}

.floating-call-dialog__avatar {
	flex-shrink: 0;
	animation: pulse-shadow 2s infinite;
}

.floating-call-dialog__text {
	overflow: hidden;
	flex: 1;
	display: flex;
	flex-direction: column;
	gap: var(--default-grid-baseline);
}

.floating-call-dialog__name {
	overflow: hidden;
	font-weight: bold;
	white-space: nowrap;
	text-overflow: ellipsis;
	margin: 0;
}

.floating-call-dialog__meta {
	display: flex;
	align-items: center;
	gap: calc(var(--default-grid-baseline) * 1);
	overflow: hidden;
	white-space: nowrap;
	color: var(--color-text-maxcontrast);
	margin: 0;

	span {
		overflow: hidden;
		text-overflow: ellipsis;
	}
}

.floating-call-dialog__meta-separator {
	flex-shrink: 0;
}

.floating-call-dialog__local-time {
	color: var(--color-text-maxcontrast);
	font-size: var(--font-size-small);
}

.floating-call-dialog__actions {
	display: flex;
	gap: calc(var(--default-grid-baseline) * 3);
}

@keyframes pulse-shadow {
	0% {
		box-shadow: 0 0 0 0 rgba(var(--color-primary-element-rgb), 0.7);
	}
	100% {
		box-shadow: 0 0 0 calc(var(--default-grid-baseline) * 2) rgba(var(--color-primary-element-rgb), 0);
	}
}
</style>
