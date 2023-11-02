<!--
  - @copyright Copyright (c) 2022, Marco Ambrosini <marcoambrosini@icloud.com>
  -
  - @author Marco Ambrosini <marcoambrosini@icloud.com>
  - @author Maksim Sukharev <antreesy.web@gmail.com>
  -
  - @license AGPL-3.0-or-later
  -
  - This program is free software: you can redistribute it and/or modify
  - it under the terms of the GNU Affero General Public License as
  - published by the Free Software Foundation, either version 3 of the
  - License, or (at your option) any later version.
  -
  - This program is distributed in the hope that it will be useful,
  - but WITHOUT ANY WARRANTY; without even the implied warranty of
  - MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
  - GNU Affero General Public License for more details.
  -
  - You should have received a copy of the GNU Affero General Public License
  - along with this program. If not, see <http://www.gnu.org/licenses/>.
  -
  -->

<template>
	<NcActions ref="attachmentsMenu"
		:container="container"
		:boundaries-element="boundariesElement"
		:disabled="disabled"
		:aria-label="t('spreed', 'Share files to the conversation')"
		:aria-haspopup="true">
		<template #icon>
			<Paperclip :size="16" />
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
				@click="handleFileShare">
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
				{{ provider.label }}
			</NcActionButton>
		</template>

		<NcActionButton v-if="canCreatePoll"
			close-after-click
			@click="$emit('toggle-poll-editor')">
			<template #icon>
				<Poll :size="20" />
			</template>
			{{ t('spreed', 'Create new poll') }}
		</NcActionButton>
	</NcActions>
</template>

<script>
import Folder from 'vue-material-design-icons/Folder.vue'
import Paperclip from 'vue-material-design-icons/Paperclip.vue'
import Poll from 'vue-material-design-icons/Poll.vue'
import Upload from 'vue-material-design-icons/Upload.vue'

import { getFilePickerBuilder } from '@nextcloud/dialogs'

import NcActionButton from '@nextcloud/vue/dist/Components/NcActionButton.js'
import NcActions from '@nextcloud/vue/dist/Components/NcActions.js'

import { shareFile } from '../../services/filesSharingServices.js'

const picker = getFilePickerBuilder(t('spreed', 'File to share'))
	.setMultiSelect(false)
	.setType(1)
	.allowDirectories()
	.build()

export default {
	name: 'NewMessageAttachments',

	components: {
		NcActionButton,
		NcActions,
		// Icons
		Folder,
		Paperclip,
		Poll,
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

	emits: ['update-new-file-dialog', 'toggle-poll-editor', 'open-file-upload'],

	computed: {
		fileTemplateOptions() {
			return this.$store.getters.getFileTemplates()
		},

		shareFromNextcloudLabel() {
			return t('spreed', 'Share from {nextcloud}', { nextcloud: OC.theme.productName })
		},
	},

	methods: {
		handleFileShare() {
			picker.pick()
				.then((path) => {
					console.debug(`path ${path} selected for sharing`)
					if (!path.startsWith('/')) {
						throw new Error(t('files', 'Invalid path selected'))
					}
					return shareFile(path, this.token)
				})

			// FIXME Remove this hack once it is possible to set the parent
			// element of the file picker.
			// By default, the file picker is a sibling of the fullscreen
			// element, so it is not visible when in fullscreen mode. It is not
			// possible to specify the parent nor to know when the file picker
			// was actually opened, so for the time being it is moved if
			// needed shortly after calling it.
			setTimeout(() => {
				if (this.$store.getters.isFullscreen()) {
					document.getElementById('content-vue').appendChild(document.querySelector('.oc-dialog'))
				}
			}, 1000)
		},
	},
}
</script>
