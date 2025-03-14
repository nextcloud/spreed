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
			<Plus :size="16" />
		</template>

		<NcActionButton v-if="canUploadFiles"
			close-after-click
			@click="$emit('open-file-upload')">
			<template #icon>
				<Upload :size="20" />
			</template>
			{{ t('spreed', 'Upload from device') }}
		</NcActionButton>

		<template v-if="canShareFiles">
			<NcActionButton close-after-click
				@click="$emit('handle-file-share')">
				<template #icon>
					<Folder :size="20" />
				</template>
				{{ shareFromNextcloudLabel }}
			</NcActionButton>

			<NcActionButton v-for="(provider, index) in fileTemplateOptions"
				:key="index"
				close-after-click
				:icon="provider.iconClass"
				@click="$emit('update-new-file-dialog', index)">
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
				<PollIcon :size="20" />
			</template>
			{{ t('spreed', 'Create new poll') }}
		</NcActionButton>

		<NcActionButton close-after-click
			@click="showSmartPicker">
			<template #icon>
				<SlashForwardBox :size="20" />
			</template>
			{{ t('spreed', 'Smart picker') }}
		</NcActionButton>
	</NcActions>
</template>

<script>
import Folder from 'vue-material-design-icons/Folder.vue'
import Plus from 'vue-material-design-icons/Plus.vue'
import PollIcon from 'vue-material-design-icons/Poll.vue'
import SlashForwardBox from 'vue-material-design-icons/SlashForwardBox.vue'
import Upload from 'vue-material-design-icons/Upload.vue'

import { t } from '@nextcloud/l10n'

import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcActions from '@nextcloud/vue/components/NcActions'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'

import { EventBus } from '../../services/EventBus.ts'

export default {
	name: 'NewMessageAttachments',

	components: {
		NcActionButton,
		NcActions,
		NcIconSvgWrapper,
		// Icons
		Folder,
		Plus,
		PollIcon,
		SlashForwardBox,
		Upload,
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

	emits: ['update-new-file-dialog', 'open-file-upload', 'handle-file-share'],

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
			EventBus.emit('poll-editor-open', { id: null, fromDrafts: false })
		},
	},
}
</script>
