<!--
  - SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<section id="vue-avatar-section">
		<div class="avatar__container">
			<div v-if="!showCropper" class="avatar__preview">
				<div v-if="emojiAvatar"
					:class="['avatar__preview-emoji', themeClass]"
					:style="{'background-color': backgroundColor}">
					{{ emojiAvatar }}
				</div>
				<ConversationIcon v-else-if="!loading"
					:item="conversation"
					:size="AVATAR.SIZE.EXTRA_LARGE"
					hide-user-status />
				<div v-else class="icon-loading" />
			</div>
			<VueCropper v-show="showCropper"
				ref="cropper"
				class="avatar__cropper"
				v-bind="cropperOptions" />
			<div v-if="editable" class="avatar__controls">
				<div class="avatar__buttons">
					<!-- Set emoji as avatar -->
					<template v-if="!showCropper">
						<NcEmojiPicker :per-line="5" container="#vue-avatar-section" @select="setEmoji">
							<NcButton :title="t('spreed', 'Set emoji as conversation picture')"
								:aria-label="t('spreed', 'Set emoji as conversation picture')">
								<template #icon>
									<EmoticonOutline :size="20" />
								</template>
							</NcButton>
						</NcEmojiPicker>
						<NcColorPicker v-if="emojiAvatar" v-model="backgroundColor" container="#vue-avatar-section">
							<NcButton :title="t('spreed', 'Set background color for conversation picture')"
								:aria-label="t('spreed', 'Set background color for conversation picture')">
								<template #icon>
									<Palette :size="20" />
								</template>
							</NcButton>
						</NcColorPicker>
					</template>

					<!-- Set picture as avatar -->
					<NcButton :title="t('settings', 'Upload conversation picture')"
						:aria-label="t('settings', 'Upload conversation picture')"
						@click="activateLocalFilePicker">
						<template #icon>
							<Upload :size="20" />
						</template>
					</NcButton>
					<NcButton :title="t('settings', 'Choose conversation picture from files')"
						:aria-label="t('settings', 'Choose conversation picture from files')"
						@click="showFilePicker = true">
						<template #icon>
							<Folder :size="20" />
						</template>
					</NcButton>

					<!-- Remove existing avatar -->
					<NcButton v-if="hasAvatar"
						:title="t('settings', 'Remove conversation picture')"
						:aria-label="t('settings', 'Remove conversation picture')"
						@click="removeAvatar">
						<template #icon>
							<Delete :size="20" />
						</template>
					</NcButton>
				</div>
				<span class="avatar__warning">
					{{ t('spreed', 'The file must be a PNG or JPG') }}
				</span>
				<input :id="inputId"
					ref="input"
					type="file"
					:accept="validMimeTypes.join(',')"
					@change="onChange">
				<div v-if="showControls" class="avatar__buttons">
					<NcButton @click="cancel">
						{{ t('spreed', 'Cancel') }}
					</NcButton>
					<NcButton v-if="!controlled"
						type="primary"
						@click="saveAvatar">
						{{ t('spreed', 'Set picture') }}
					</NcButton>
				</div>
			</div>
		</div>

		<FilePickerVue v-if="showFilePicker"
			:name="t('spreed', 'Choose your conversation picture')"
			container="#vue-avatar-section"
			:buttons="filePickerButtons"
			:multiselect="false"
			:mimetype-filter="validMimeTypes"
			@close="showFilePicker = false" />
	</section>
</template>

<script>
import VueCropper from 'vue-cropperjs'

import Delete from 'vue-material-design-icons/Delete.vue'
import EmoticonOutline from 'vue-material-design-icons/EmoticonOutline.vue'
import Folder from 'vue-material-design-icons/Folder.vue'
import Palette from 'vue-material-design-icons/Palette.vue'
import Upload from 'vue-material-design-icons/Upload.vue'

import { getRequestToken } from '@nextcloud/auth'
import axios from '@nextcloud/axios'
// eslint-disable-next-line
// import { showError } from '@nextcloud/dialogs'
import { FilePickerVue } from '@nextcloud/dialogs/filepicker.js'
import { generateUrl } from '@nextcloud/router'

import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcColorPicker from '@nextcloud/vue/dist/Components/NcColorPicker.js'
import NcEmojiPicker from '@nextcloud/vue/dist/Components/NcEmojiPicker.js'

import ConversationIcon from '../ConversationIcon.vue'

import { AVATAR } from '../../constants.js'
import { isDarkTheme } from '../../utils/isDarkTheme.js'

// eslint-disable-next-line n/no-extraneous-import
import 'cropperjs/dist/cropper.css'

const validMimeTypes = ['image/png', 'image/jpeg']

export default {
	name: 'ConversationAvatarEditor',

	components: {
		ConversationIcon,
		FilePickerVue,
		NcButton,
		NcColorPicker,
		NcEmojiPicker,
		VueCropper,
		// Icons
		Delete,
		EmoticonOutline,
		Folder,
		Palette,
		Upload,
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

	emits: ['avatar-edited'],

	setup() {
		return {
			AVATAR,
			validMimeTypes,
		}
	},

	data() {
		return {
			showCropper: false,
			showFilePicker: false,
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
			return `avatar__preview-emoji--${isDarkTheme ? 'dark' : 'bright'}`
		},

		showControls() {
			return this.editable && (this.showCropper || this.emojiAvatar)
		},

		filePickerButtons() {
			return [{
				label: t('spreed', 'Choose'),
				callback: (nodes) => this.handleFileChoose(nodes),
				type: 'primary'
			}]
		},
	},

	watch: {
		showCropper(value) {
			if (this.controlled) {
				this.$emit('avatar-edited', value)
			}
		},
		emojiAvatar(value) {
			if (this.controlled) {
				this.$emit('avatar-edited', !!value)
			}
		},
	},

	expose: ['saveAvatar'],

	methods: {
		activateLocalFilePicker() {
			// Set to null so that selecting the same file will trigger the change event
			this.$refs.input.value = null
			this.$refs.input.click()
		},

		onChange(e) {
			this.loading = true
			const file = e.target.files[0]
			if (!this.validMimeTypes.includes(file.type)) {
				window.OCP.Toast.error(t('spreed', 'Please select a valid PNG or JPG file'))
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

		async handleFileChoose(nodes) {
			const path = nodes[0]?.path
			if (!path) {
				return
			}

			this.loading = true
			try {
				const { data } = await axios.post(generateUrl('/avatar'), { path })
				if (data.status === 'success') {
					this.loading = false
				} else if (data.data === 'notsquare') {
					const tempAvatar = generateUrl('/avatar/tmp') + '?requesttoken=' + encodeURIComponent(getRequestToken()) + '#' + Math.floor(Math.random() * 1000)
					this.$refs.cropper.replace(tempAvatar)
					this.showCropper = true
				} else {
					window.OCP.Toast.error(data.data.message)
					this.cancel()
				}
			} catch (e) {
				window.OCP.Toast.error(t('spreed', 'Error setting conversation picture'))
				this.cancel()
			}
		},

		setEmoji(emoji) {
			this.emojiAvatar = emoji
		},

		saveAvatar() {
			this.loading = true

			if (this.emojiAvatar) {
				this.saveEmojiAvatar()
			} else {
				this.savePictureAvatar()
			}
		},

		async saveEmojiAvatar() {
			try {
				await this.$store.dispatch('setConversationEmojiAvatarAction', {
					token: this.conversation.token,
					emoji: this.emojiAvatar,
					color: this.backgroundColor ? this.backgroundColor.slice(1) : null,
				})
				this.emojiAvatar = ''
				this.backgroundColor = ''
			} catch (error) {
				window.OCP.Toast.error(t('spreed', 'Could not set the conversation picture: {error}',
					{ error: error.message },
				))
			} finally {
				this.loading = false
			}
		},

		savePictureAvatar() {
			this.showCropper = false
			const canvasData = this.$refs.cropper.getCroppedCanvas()
			const scaleFactor = canvasData.width > 512 ? 512 / canvasData.width : 1
			this.$refs.cropper.scale(scaleFactor, scaleFactor).getCroppedCanvas().toBlob(async (blob) => {
				if (blob === null) {
					window.OCP.Toast.error(t('spreed', 'Error cropping conversation picture'))
					this.cancel()
					return
				}

				const formData = new FormData()
				formData.append('file', blob)

				try {
					await this.$store.dispatch('setConversationAvatarAction', {
						token: this.conversation.token,
						file: formData,
					})
				} catch (error) {
					window.OCP.Toast.error(t('spreed', 'Could not set the conversation picture: {error}',
						{ error: error.message },
					))
				} finally {
					this.loading = false
				}
			})
		},

		async removeAvatar() {
			this.loading = true
			try {
				await this.$store.dispatch('deleteConversationAvatarAction', {
					token: this.conversation.token,
				})
			} catch (e) {
				window.OCP.Toast.error(t('spreed', 'Error removing conversation picture'))
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
