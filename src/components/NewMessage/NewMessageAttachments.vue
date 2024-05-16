<!--
  - SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcActions ref="attachmentsMenu"
		:container="container"
		:boundaries-element="boundariesElement"
		:disabled="disabled"
		:force-menu="true"
		:aria-label="t('spreed', 'Share files to the conversation')"
		:aria-haspopup="true">
		<template #icon>
			<Paperclip :size="16" />
		</template>

		<NcActionButton v-if="canUploadFiles"
			close-after-click
			@click="$emit('openFileUpload')">
			<template #icon>
				<Upload :size="20" />
			</template>
			{{ t('spreed', 'Upload from device') }}
		</NcActionButton>

		<template v-if="canShareFiles">
			<NcActionButton close-after-click
				@click="$emit('handleFileShare')">
				<template #icon>
					<Folder :size="20" />
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
			@click="$emit('togglePollEditor')">
			<template #icon>
				<PollIcon :size="20" />
			</template>
			{{ t('spreed', 'Create new poll') }}
		</NcActionButton>
	</NcActions>
</template>

<script>
import Folder from 'vue-material-design-icons/Folder.vue'
import Paperclip from 'vue-material-design-icons/Paperclip.vue'
import PollIcon from 'vue-material-design-icons/Poll.vue'
import Upload from 'vue-material-design-icons/Upload.vue'

import NcActionButton from '@nextcloud/vue/dist/Components/NcActionButton.js'
import NcActions from '@nextcloud/vue/dist/Components/NcActions.js'
import NcIconSvgWrapper from '@nextcloud/vue/dist/Components/NcIconSvgWrapper.js'

export default {
	name: 'NewMessageAttachments',

	components: {
		NcActionButton,
		NcActions,
		NcIconSvgWrapper,
		// Icons
		Folder,
		Paperclip,
		PollIcon,
		Upload,
	},

	props: {
		token: {
			type: String,
			required: true,
		},

		container: {
			type: String,
			required: true,
		},

		boundariesElement: {
			type: Element,
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

	emits: ['updateNewFileDialog', 'togglePollEditor', 'openFileUpload', 'handleFileShare'],

	computed: {
		fileTemplateOptions() {
			return this.$store.getters.getFileTemplates()
		},

		shareFromNextcloudLabel() {
			return t('spreed', 'Share from {nextcloud}', { nextcloud: OC.theme.productName })
		},
	},
}
</script>
