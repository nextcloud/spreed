<!--
  - SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div v-if="hasFiles" class="upload-editor">
		<TransitionWrapper
			v-if="!isVoiceMessage"
			class="upload-editor__previews"
			name="fade"
			tag="div"
			group>
			<FilePreview
				v-for="file in files"
				:key="file[1].temporaryMessage.id"
				:token="token"
				isUploadEditor
				:file="file[1].temporaryMessage.messageParameters.file"
				@removeFile="removeFile" />
			<NcButton
				:aria-label="addMoreAriaLabel"
				:title="addMoreAriaLabel"
				variant="tertiary"
				class="upload-editor__add-more"
				size="large"
				@click="$emit('openFilePicker')">
				<template #icon>
					<IconPlus :size="48" />
				</template>
			</NcButton>
		</TransitionWrapper>
		<div v-else class="upload-editor__voice-message">
			<AudioPlayer
				:name="voiceMessageName"
				:localUrl="voiceMessageLocalURL" />
			<NcButton
				variant="error"
				:aria-label="t('spreed', 'Dismiss')"
				:title="t('spreed', 'Dismiss')"
				@click="removeFile(firstFile.temporaryMessage.id)">
				<template #icon>
					<IconClose :size="20" />
				</template>
			</NcButton>
		</div>

		<NcCheckboxRadioSwitch
			v-if="!isVoiceMessage && supportConversationSubfolders"
			v-model="uploadStore.allowUpdate"
			type="switch">
			{{ t('spreed', 'Allow editing of uploaded files') }}
		</NcCheckboxRadioSwitch>
		<NcCheckboxRadioSwitch
			v-if="hasImages"
			v-model="uploadStore.skipCompression"
			type="switch">
			{{ t('spreed', 'Send images without compression') }}
		</NcCheckboxRadioSwitch>
	</div>
</template>

<script>
import { t } from '@nextcloud/l10n'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import IconClose from 'vue-material-design-icons/Close.vue'
import IconPlus from 'vue-material-design-icons/Plus.vue'
import AudioPlayer from '../MessagesList/MessagesGroup/Message/MessagePart/AudioPlayer.vue'
import FilePreview from '../MessagesList/MessagesGroup/Message/MessagePart/FilePreview.vue'
import TransitionWrapper from '../UIShared/TransitionWrapper.vue'
import { useGetToken } from '../../composables/useGetToken.ts'
import { useUploadFiles } from '../../composables/useUploadFiles.ts'
import { useUploadStore } from '../../stores/upload.ts'

export default {
	name: 'NewMessageUploadEditor',

	components: {
		FilePreview,
		IconClose,
		IconPlus,
		AudioPlayer,
		NcButton,
		NcCheckboxRadioSwitch,
		TransitionWrapper,
	},

	emits: ['openFilePicker'],

	setup() {
		const token = useGetToken()
		const uploadStore = useUploadStore()

		const {
			files,
			hasFiles,
			firstFile,
			isVoiceMessage,
			hasImages,
			supportConversationSubfolders,
			removeFile,
		} = useUploadFiles(token)

		return {
			files,
			hasFiles,
			firstFile,
			isVoiceMessage,
			hasImages,
			supportConversationSubfolders,
			removeFile,
			token,
			uploadStore,
		}
	},

	computed: {
		addMoreAriaLabel() {
			return t('spreed', 'Add more files')
		},

		voiceMessageName() {
			if (!this.firstFile?.file?.name) {
				return ''
			}
			return this.firstFile.file.name
		},

		voiceMessageLocalURL() {
			return this.uploadStore.getLocalUrl(this.firstFile.temporaryMessage.referenceId)
		},
	},

	methods: {
		t,
	},
}
</script>

<style lang="scss" scoped>
.upload-editor {
	display: flex;
	flex-direction: column;
	gap: var(--default-grid-baseline);
	margin-block-end: var(--default-grid-baseline);

	&__previews {
		display: flex;
		flex-wrap: wrap;
		align-items: center;
		// Cap the previews area, so the chat input stays visible
		max-height: 40vh;
		overflow-y: auto;
	}

	&__voice-message {
		display: flex;
		align-items: center;
		gap: var(--default-grid-baseline);
		// Stretch over the available width, which is capped by the chat input
		width: 100%;

		:deep(.audio-player) {
			flex-grow: 1;
			min-width: 0;
		}

		// The native audio element has an intrinsic width, override it
		:deep(.audio-player__audio) {
			width: 100%;
		}
	}

	&__add-more {
		// Match the FilePreview tiles next to it
		width: 140px !important;
		height: 140px !important;
		margin: 10px;

		:deep(.button-vue__icon) {
			border-radius: var(--border-radius-pill);
			color: var(--color-primary-element-text);
			background-color: var(--color-primary-element);
		}
	}
}

</style>
