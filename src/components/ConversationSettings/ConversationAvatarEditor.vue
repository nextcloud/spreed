<!--
  - SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<section id="vue-avatar-section">
		<div class="avatar__container">
			<div v-if="!showCropper" class="avatar__preview">
				<div
					v-if="emojiAvatar"
					class="avatar__preview-emoji"
					:class="themeClass"
					:style="{ 'background-color': backgroundColor }">
					{{ emojiAvatar }}
				</div>
				<ConversationIcon
					v-else-if="!loading"
					:item="conversation"
					:size="AVATAR.SIZE.EXTRA_LARGE"
					hide-user-status />
				<div v-else class="icon-loading" />
			</div>
			<VueCropper
				v-show="showCropper"
				ref="cropper"
				class="avatar__cropper"
				v-bind="cropperOptions" />
			<div v-if="editable" class="avatar__controls">
				<div class="avatar__buttons">
					<!-- Set emoji as avatar -->
					<template v-if="!showCropper">
						<NcEmojiPicker :per-line="5" container="#vue-avatar-section" @select="setEmoji">
							<NcButton
								:title="t('spreed', 'Set emoji as conversation picture')"
								:aria-label="t('spreed', 'Set emoji as conversation picture')">
								<template #icon>
									<IconEmoticonOutline :size="20" />
								</template>
							</NcButton>
						</NcEmojiPicker>
						<NcColorPicker
							v-if="emojiAvatar"
							v-model="backgroundColor"
							advanced-fields
							container="#vue-avatar-section">
							<NcButton
								:title="t('spreed', 'Set background color for conversation picture')"
								:aria-label="t('spreed', 'Set background color for conversation picture')">
								<template #icon>
									<IconPaletteOutline :size="20" />
								</template>
							</NcButton>
						</NcColorPicker>
					</template>

					<!-- Set picture as avatar -->
					<NcButton
						:title="t('spreed', 'Upload conversation picture')"
						:aria-label="t('spreed', 'Upload conversation picture')"
						@click="activateLocalFilePicker">
						<template #icon>
							<NcIconSvgWrapper :svg="IconFileUpload" :size="20" />
						</template>
					</NcButton>
					<NcButton
						:title="t('spreed', 'Choose conversation picture from files')"
						:aria-label="t('spreed', 'Choose conversation picture from files')"
						@click="showFilePicker">
						<template #icon>
							<IconFolder :size="20" />
						</template>
					</NcButton>

					<!-- Remove existing avatar -->
					<NcButton
						v-if="hasAvatar"
						:title="t('spreed', 'Remove conversation picture')"
						:aria-label="t('spreed', 'Remove conversation picture')"
						@click="removeAvatar">
						<template #icon>
							<IconTrashCanOutline :size="20" />
						</template>
					</NcButton>
				</div>
				<span class="avatar__warning">
					{{ t('spreed', 'The file must be a PNG or JPG') }}
				</span>
				<input
					:id="inputId"
					ref="input"
					type="file"
					:accept="validMimeTypes.join(',')"
					@change="onChange">
				<div v-if="showControls" class="avatar__buttons">
					<NcButton @click="cancel">
						{{ t('spreed', 'Cancel') }}
					</NcButton>
					<NcButton
						v-if="!controlled"
						variant="primary"
						@click="saveAvatar">
						{{ t('spreed', 'Set picture') }}
					</NcButton>
				</div>
			</div>
		</div>
	</section>
</template>

<script>
import { showError } from '@nextcloud/dialogs'
import { getFilePickerBuilder } from '@nextcloud/dialogs'
import { t } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'
import { useIsDarkTheme } from '@nextcloud/vue/composables/useIsDarkTheme'
import VueCropper from 'vue-cropperjs'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcColorPicker from '@nextcloud/vue/components/NcColorPicker'
import NcEmojiPicker from '@nextcloud/vue/components/NcEmojiPicker'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import IconEmoticonOutline from 'vue-material-design-icons/EmoticonOutline.vue'
import IconFolder from 'vue-material-design-icons/Folder.vue' // Filled as in Files app icon
import IconPaletteOutline from 'vue-material-design-icons/PaletteOutline.vue'
import IconTrashCanOutline from 'vue-material-design-icons/TrashCanOutline.vue'
import ConversationIcon from '../ConversationIcon.vue'
import IconFileUpload from '../../../img/material-icons/file-upload.svg?raw'
import { AVATAR } from '../../constants.ts'

import 'cropperjs/dist/cropper.css'

const validMimeTypes = ['image/png', 'image/jpeg']

export default {
	name: 'ConversationAvatarEditor',

	components: {
		ConversationIcon,
		NcButton,
		NcColorPicker,
		NcEmojiPicker,
		NcIconSvgWrapper,
		VueCropper,
		// Icons
		IconTrashCanOutline,
		IconEmoticonOutline,
		IconFolder,
		IconPaletteOutline,
	},

	props: {
		conversation: {
			type: Object,
			required: true,
		},

		/**
		 * Shows or hides the editing buttons.
		 */
		editable: {
			type: Boolean,
			default: false,
		},

		/**
		 * Force component to emit signals and be used from parent components
		 */
		controlled: {
			type: Boolean,
			default: false,
		},
	},

	emits: ['avatarEdited'],

	expose: ['saveAvatar', 'getPictureFormData', 'emojiAvatar', 'backgroundColor'],

	setup() {
		const isDarkTheme = useIsDarkTheme()
		return {
			IconFileUpload,
			isDarkTheme,
			AVATAR,
			validMimeTypes,
		}
	},

	data() {
		return {
			showCropper: false,
			loading: false,
			cropperOptions: {
				aspectRatio: 1,
				viewMode: 1,
				guides: false,
				center: false,
				highlight: false,
				autoCropArea: 1,
				minContainerWidth: 300,
				minContainerHeight: 300,
			},

			backgroundColor: '',
			emojiAvatar: '',
		}
	},

	computed: {
		inputId() {
			return `account-property-${this.conversation.displayName}`
		},

		hasAvatar() {
			return this.conversation.isCustomAvatar
		},

		themeClass() {
			return `avatar__preview-emoji--${this.isDarkTheme ? 'dark' : 'bright'}`
		},

		showControls() {
			return this.editable && (this.showCropper || this.emojiAvatar)
		},
	},

	watch: {
		showCropper(value) {
			if (this.controlled) {
				this.$emit('avatarEdited', value)
			}
		},

		emojiAvatar(value) {
			if (this.controlled) {
				this.$emit('avatarEdited', !!value)
			}
		},
	},

	methods: {
		t,
		activateLocalFilePicker() {
			// Set to null so that selecting the same file will trigger the change event
			this.$refs.input.value = null
			this.$refs.input.click()
		},

		onChange(e) {
			this.loading = true
			const file = e.target.files[0]
			if (!this.validMimeTypes.includes(file.type)) {
				showError(t('spreed', 'Please select a valid PNG or JPG file'))
				this.cancel()
				return
			}

			const reader = new FileReader()
			reader.onload = (e) => {
				this.$refs.cropper.replace(e.target.result)
				this.showCropper = true
			}
			reader.readAsDataURL(file)
		},

		async showFilePicker() {
			const filePicker = getFilePickerBuilder(t('spreed', 'Choose your conversation picture'))
				.setContainer('#vue-avatar-section')
				.setMultiSelect(false)
				.addMimeTypeFilter('image/png')
				.addMimeTypeFilter('image/jpeg') // FIXME upstream: pass as array
				.addButton({
					label: t('spreed', 'Choose'),
					callback: (nodes) => this.handleFileChoose(nodes),
					variant: 'primary',
				})
				.build()
			await filePicker.pickNodes()
		},

		async handleFileChoose(nodes) {
			const fileid = nodes[0]?.fileid
			if (!fileid) {
				return
			}

			try {
				const tempAvatar = generateUrl(`/core/preview?fileId=${fileid}&x=512&y=512&a=1`)
				this.$refs.cropper.replace(tempAvatar)
				this.showCropper = true
			} catch (e) {
				showError(t('spreed', 'Error setting conversation picture'))
				this.cancel()
			}
		},

		setEmoji(emoji) {
			this.emojiAvatar = emoji
		},

		async saveAvatar() {
			this.loading = true

			try {
				if (this.emojiAvatar) {
					await this.saveEmojiAvatar()
				} else {
					await this.savePictureAvatar()
				}
			} catch (error) {
				showError(t('spreed', 'Could not set the conversation picture: {error}', { error: error.message }))
				this.cancel()
			} finally {
				this.loading = false
			}
		},

		async saveEmojiAvatar() {
			await this.$store.dispatch('setConversationEmojiAvatarAction', {
				token: this.conversation.token,
				emoji: this.emojiAvatar,
				color: this.backgroundColor ? this.backgroundColor.slice(1) : null,
			})
			this.emojiAvatar = ''
			this.backgroundColor = ''
		},

		async getPictureFormData() {
			const canvasData = this.$refs.cropper.getCroppedCanvas()
			const scaleFactor = canvasData.width > 512 ? 512 / canvasData.width : 1

			const blob = await new Promise((resolve, reject) => {
				this.$refs.cropper.scale(scaleFactor, scaleFactor).getCroppedCanvas()
					.toBlob((blob) => blob === null
						? reject(new Error(t('spreed', 'Error cropping conversation picture')))
						: resolve(blob))
			})
			const formData = new FormData()
			formData.append('file', blob)
			return formData
		},

		async savePictureAvatar() {
			this.showCropper = false
			const file = await this.getPictureFormData()

			await this.$store.dispatch('setConversationAvatarAction', {
				token: this.conversation.token,
				file,
			})
		},

		async removeAvatar() {
			this.loading = true
			try {
				await this.$store.dispatch('deleteConversationAvatarAction', {
					token: this.conversation.token,
				})
			} catch (e) {
				showError(t('spreed', 'Error removing conversation picture'))
			} finally {
				this.loading = false
			}
		},

		cancel() {
			this.showCropper = false
			this.loading = false
			this.emojiAvatar = ''
			this.backgroundColor = ''
		},
	},
}
</script>

<style lang="scss" scoped>
section {
	grid-row: 1/3;
}

.avatar {
	&__container {
		display: flex;
		flex-flow: row wrap;
		justify-content: center;
		align-items: flex-start;
		gap: 16px;
	}

	&__controls {
		display: flex;
		flex-direction: column;
		align-items: center;
		gap: 16px;
	}

	&__warning {
		color: var(--color-text-maxcontrast);
	}

	&__preview {
		display: flex;
		justify-content: center;
		align-items: center;
		flex-shrink: 0;
		width: 300px;
		height: 180px;
		padding: 0 60px;

		&-emoji {
			display: flex;
			justify-content: center;
			align-items: center;
			width: 100%;
			height: 100%;
			padding-bottom: 6px;
			border-radius: 100%;
			background-color: var(--color-text-maxcontrast);
			font-size: 575%;
			line-height: 100%;

			&--dark {
				background-color: #3B3B3B;
			}
		}
	}

	&__buttons {
		display: flex;
		gap: 10px;
	}

	&__cropper {
		width: 300px;
		height: 300px;
		overflow: hidden;

		&:deep(.cropper-view-box) {
			border-radius: 50%;
		}
	}
}

input[type="file"] {
	display: none;
}

</style>
