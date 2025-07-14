<!--
  - SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcActions ref="attachmentsMenu"
		:disabled="disabled"
		:force-menu="true"
		:aria-label="t('spreed', 'Share files to the conversation')"
		:aria-haspopup="true">
		<template #icon>
			<IconPlus :size="16" />
		</template>

		<NcActionButton v-if="canUploadFiles"
			close-after-click
			@click="$emit('openFileUpload')">
			<template #icon>
				<NcIconSvgWrapper :svg="IconFileUpload" :size="20" />
			</template>
			{{ t('spreed', 'Upload from device') }}
		</NcActionButton>

		<template v-if="canShareFiles">
			<NcActionButton close-after-click
				@click="$emit('handleFileShare')">
				<template #icon>
					<IconFolder :size="20" />
				</template>
				{{ shareFromNextcloudLabel }}
			</NcActionButton>

			<NcActionButton v-for="(provider, index) in fileTemplateOptions"
				:key="index"
				close-after-click
				:icon="provider.iconClass"
				@click="$emit('updateNewFileDialog', index)">
				<template v-if="provider.iconSvgInline" #icon>
					<NcIconSvgWrapper :svg="provider.iconSvgInline" :size="20" />
				</template>
				{{ provider.label }}
			</NcActionButton>
		</template>

		<NcActionButton v-if="canCreatePoll"
			close-after-click
			@click="showPollEditor">
			<template #icon>
				<IconPoll :size="20" />
			</template>
			{{ t('spreed', 'Create new poll') }}
		</NcActionButton>

		<NcActionButton close-after-click
			@click="showSmartPicker">
			<template #icon>
				<NcIconSvgWrapper :svg="IconSmartPicker" :size="20" />
			</template>
			{{ t('spreed', 'Smart picker') }}
		</NcActionButton>
	</NcActions>
</template>

<script>
import { t } from '@nextcloud/l10n'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcActions from '@nextcloud/vue/components/NcActions'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import IconFolder from 'vue-material-design-icons/Folder.vue' // Filled as in Files app icon
import IconPlus from 'vue-material-design-icons/Plus.vue'
import IconPoll from 'vue-material-design-icons/Poll.vue'
import IconFileUpload from '../../../img/material-icons/file-upload.svg?raw'
import IconSmartPicker from '../../../img/material-icons/smart-picker.svg?raw'
import { EventBus } from '../../services/EventBus.ts'

export default {
	name: 'NewMessageAttachments',

	components: {
		NcActionButton,
		NcActions,
		NcIconSvgWrapper,
		// Icons
		IconFolder,
		IconPlus,
		IconPoll,
	},

	props: {
		token: {
			type: String,
			required: true,
		},

		disabled: {
			type: Boolean,
			required: true,
		},

		canShareFiles: {
			type: Boolean,
			required: true,
		},

		canUploadFiles: {
			type: Boolean,
			required: true,
		},

		canCreatePoll: {
			type: Boolean,
			required: true,
		},
	},

	emits: ['updateNewFileDialog', 'openFileUpload', 'handleFileShare'],

	setup() {
		return {
			IconFileUpload,
			IconSmartPicker,
		}
	},

	computed: {
		fileTemplateOptions() {
			return this.$store.getters.fileTemplates
		},

		shareFromNextcloudLabel() {
			return IS_DESKTOP
				? t('spreed', 'Share from {nextcloud}', { nextcloud: OC.theme.productName })
				: t('spreed', 'Share from Files')
		},
	},

	methods: {
		t,

		showSmartPicker() {
			EventBus.emit('smart-picker-open')
		},

		showPollEditor() {
			EventBus.emit('poll-editor-open', { token: this.token, id: null, fromDrafts: false })
		},
	},
}
</script>
