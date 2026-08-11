<!--
  - SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div v-if="hasFiles" class="upload-editor">
		<div v-if="showImageQuality || showSharePermission" class="upload-editor__options">
			<NcActions
				v-if="showSharePermission"
				:variant="uploadStore.allowUpdate ? 'secondary' : 'tertiary'"
				size="small"
				:menuName="sharePermissionLabel">
				<template #icon>
					<IconPencilOutline v-if="uploadStore.allowUpdate" :size="20" />
					<IconPencilOffOutline v-else :size="20" />
				</template>
				<NcActionButton
					v-model="sharePermission"
					type="radio"
					value="view-only"
					closeAfterClick>
					<template #icon>
						<IconPencilOffOutline :size="20" />
					</template>
					{{ labels.viewOnly }}
				</NcActionButton>
				<NcActionButton
					v-model="sharePermission"
					type="radio"
					value="editable"
					closeAfterClick>
					<template #icon>
						<IconPencilOutline :size="20" />
					</template>
					{{ labels.editable }}
				</NcActionButton>
			</NcActions>

			<NcActions
				v-if="showImageQuality"
				:variant="uploadStore.skipCompression ? 'secondary' : 'tertiary'"
				size="small"
				:menuName="imageQualityLabel">
				<template #icon>
					<IconHighDefinition v-if="uploadStore.skipCompression" :size="20" />
					<IconStandardDefinition v-else :size="20" />
				</template>
				<NcActionButton
					v-model="imageQuality"
					type="radio"
					value="standard"
					closeAfterClick>
					<template #icon>
						<IconStandardDefinition :size="20" />
					</template>
					{{ labels.standardDefinition }}
				</NcActionButton>
				<NcActionButton
					v-model="imageQuality"
					type="radio"
					value="high"
					closeAfterClick>
					<template #icon>
						<IconHighDefinition :size="20" />
					</template>
					{{ labels.highDefinition }}
				</NcActionButton>
			</NcActions>
		</div>

		<TransitionWrapper
			v-if="!isVoiceMessage"
			class="upload-editor__previews"
			name="fade"
			tag="div"
			group>
			<NcButton
				key="add-more"
				:aria-label="addMoreAriaLabel"
				:title="addMoreAriaLabel"
				variant="tertiary"
				class="upload-editor__add-more"
				size="large"
				wide
				@click="$emit('openFilePicker')">
				<template #icon>
					<IconPlus :size="48" />
				</template>
			</NcButton>
			<FilePreview
				v-for="file in files"
				:key="file[1].temporaryMessage.id"
				:token="token"
				isUploadEditor
				:file="file[1].temporaryMessage.messageParameters.file"
				@removeFile="removeFile" />
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
	</div>
</template>

<script>
import { t } from '@nextcloud/l10n'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcActions from '@nextcloud/vue/components/NcActions'
import NcButton from '@nextcloud/vue/components/NcButton'
import IconClose from 'vue-material-design-icons/Close.vue'
import IconHighDefinition from 'vue-material-design-icons/HighDefinition.vue'
import IconPencilOffOutline from 'vue-material-design-icons/PencilOffOutline.vue'
import IconPencilOutline from 'vue-material-design-icons/PencilOutline.vue'
import IconPlus from 'vue-material-design-icons/Plus.vue'
import IconStandardDefinition from 'vue-material-design-icons/StandardDefinition.vue'
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
		IconPencilOffOutline,
		IconPencilOutline,
		IconPlus,
		IconHighDefinition,
		IconStandardDefinition,
		AudioPlayer,
		NcActionButton,
		NcActions,
		NcButton,
		TransitionWrapper,
	},

	emits: ['openFilePicker'],

	setup() {
		const token = useGetToken()
		const uploadStore = useUploadStore()

		const labels = {
			standardDefinition: t('spreed', 'Standard image quality'),
			highDefinition: t('spreed', 'Original image quality'),
			viewOnly: t('spreed', 'View-only for others'),
			editable: t('spreed', 'Editable by others'),
		}

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
			labels,
		}
	},

	computed: {
		addMoreAriaLabel() {
			return t('spreed', 'Add more files')
		},

		showImageQuality() {
			return this.hasImages
		},

		showSharePermission() {
			return !this.isVoiceMessage && this.supportConversationSubfolders
		},

		imageQuality: {
			get() {
				return this.uploadStore.skipCompression ? 'high' : 'standard'
			},

			set(value) {
				this.uploadStore.skipCompression = value === 'high'
			},
		},

		imageQualityLabel() {
			return this.uploadStore.skipCompression
				? this.labels.highDefinition
				: this.labels.standardDefinition
		},

		sharePermission: {
			get() {
				return this.uploadStore.allowUpdate ? 'editable' : 'view-only'
			},

			set(value) {
				this.uploadStore.allowUpdate = value === 'editable'
			},
		},

		sharePermissionLabel() {
			return this.uploadStore.allowUpdate
				? this.labels.editable
				: this.labels.viewOnly
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
	// Size of a thumbnail in .file-preview--upload-editor (FilePreview.vue).
	// Every other size below is derived from it, so this is the single knob
	--preview-size: 80px;
	--preview-padding: calc(var(--default-grid-baseline) * 2);
	--preview-name-height: 24px;
	// The rendered height of a tile is its content plus the padding around it.
	// A row adds the margin of a tile and the gap to the row below
	--preview-row-height: calc(
		var(--preview-size) + var(--preview-name-height)
		+ var(--preview-padding) * 2 + var(--default-grid-baseline) * 3
	);
	display: flex;
	flex-direction: column;
	padding: var(--default-grid-baseline);
	margin-block-end: var(--default-grid-baseline);
	border-radius: var(--border-radius-large);
	border: 2px solid var(--color-border);
	background-color: var(--color-main-background);

	&__options {
		display: flex;
		flex-wrap: wrap;
		align-items: center;
		gap: var(--default-grid-baseline);

		// Let a menu shrink below its label, which NcButton already truncates,
		// instead of pushing the row wider than the sidebar
		:deep(.action-item) {
			min-width: 0;
		}

		// NcButton sets its own font size, so it has to be overridden here
		:deep(.button-vue) {
			font-size: var(--font-size-small);
			max-width: 100%;
		}
	}

	&__previews {
		display: flex;
		flex-wrap: wrap;
		align-items: center;
		align-content: flex-start;
		gap: var(--default-grid-baseline);
		// Show one and a half rows, so it is clear that the area scrolls
		max-height: calc(var(--preview-row-height) * 1.5);
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
		// Match the thumbnail of the FilePreview tiles next to it. NcButton forces
		// a square on an icon-only button with a selector this rule can not
		// outweigh, so the `wide` prop opts out of that sizing
		width: var(--preview-size) !important;
		height: var(--preview-size) !important;
		// NcButton drives its inline padding from a variable and sets its block
		// padding to 1px, so both are replaced to match the tile
		padding: var(--preview-padding) !important;
		// The button is one padding narrower than a tile, so it takes that
		// padding on top of the margin of a tile to stay aligned with them
		margin: calc(var(--default-grid-baseline) + var(--preview-padding));
		margin-block-end: auto;
		background-color: var(--color-primary-element-light) !important;

		:deep(.button-vue__icon) {
			border-radius: var(--border-radius-pill);
			color: var(--color-primary-element);
		}
	}
}

</style>
