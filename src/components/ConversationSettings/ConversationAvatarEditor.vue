<!--
	- @copyright 2022 Christopher Ng <chrng8@gmail.com>
	-
	- @author Christopher Ng <chrng8@gmail.com>
	- @author Marco Ambrosini <marcoambrosini@icloud.com>

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
	<section id="vue-avatar-section">
		<div v-if="!showCropper" class="avatar__container">
			<div class="avatar__preview">
				<div v-if="emojiAvatar"
					class="avatar__preview-emoji"
					:style="{'background-color': backgroundColor}">
					{{ emojiAvatar }}
				</div>
				<ConversationIcon v-else-if="!loading"
					:item="conversation"
					:is-big="true"
					:disable-menu="true" />
				<div v-else class="icon-loading" />
			</div>
			<template v-if="editable">
				<div class="avatar__buttons">
					<NcEmojiPicker :per-line="5"
						@select="setEmoji">
						<NcButton :aria-label="t('spreed', 'Set emoji as profile picture')">
							<template #icon>
								<EmoticonOutline :size="20" />
							</template>
						</NcButton>
					</NcEmojiPicker>
					<NcColorPicker v-if="emojiAvatar" v-model="backgroundColor">
						<NcButton :aria-label="t('spreed', 'Set background color for profile picture')">
							<template #icon>
								<Palette :size="20" />
							</template>
						</NcButton>
					</NcColorPicker>
					<NcButton :aria-label="t('settings', 'Upload profile picture')"
						@click="activateLocalFilePicker">
						<template #icon>
							<Upload :size="20" />
						</template>
					</NcButton>
					<NcButton :aria-label="t('settings', 'Choose profile picture from files')"
						@click="openFilePicker">
						<template #icon>
							<Folder :size="20" />
						</template>
					</NcButton>
					<NcButton v-if="hasAvatar"
						:aria-label="t('settings', 'Remove profile picture')"
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
			</template>
		</div>

		<!-- Use v-show to ensure early cropper ref availability -->
		<div v-if="editable" class="avatar__container">
			<VueCropper v-show="showCropper"
				ref="cropper"
				class="avatar__cropper"
				v-bind="cropperOptions" />
			<div v-show="isEdited" class="avatar__buttons">
				<NcButton @click="cancel">
					{{ t('spreed', 'Cancel') }}
				</NcButton>
				<NcButton type="primary"
					@click="saveAvatar">
					{{ t('spreed', 'Set as conversation picture') }}
				</NcButton>
			</div>
		</div>
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
import { getFilePickerBuilder, showError } from '@nextcloud/dialogs'
import { generateUrl } from '@nextcloud/router'

import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcColorPicker from '@nextcloud/vue/dist/Components/NcColorPicker.js'
import NcEmojiPicker from '@nextcloud/vue/dist/Components/NcEmojiPicker.js'

import ConversationIcon from '../ConversationIcon.vue'

// eslint-disable-next-line n/no-extraneous-import
import 'cropperjs/dist/cropper.css'

const VALID_MIME_TYPES = ['image/png', 'image/jpeg']

const picker = getFilePickerBuilder(t('spreed', 'Choose your profile picture'))
	.setMultiSelect(false)
	.setMimeTypeFilter(VALID_MIME_TYPES)
	.setModal(true)
	.setType(1)
	.allowDirectories(false)
	.build()

export default {
	name: 'ConversationAvatarEditor',

	components: {
		ConversationIcon,
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
	},

	data() {
		return {
			showCropper: false,
			loading: false,
			validMimeTypes: VALID_MIME_TYPES,
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
			return !!this.conversation.avatarVersion
		},

		isEdited() {
			return this.showCropper || this.emojiAvatar
		},
	},

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

		async openFilePicker() {
			const path = await picker.pick()
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
					showError(data.data.message)
					this.cancel()
				}
			} catch (e) {
				showError(t('spreed', 'Error setting profile picture'))
				this.cancel()
			}
		},

		setEmoji(emoji) {
			this.emojiAvatar = emoji
		},

		saveAvatar() {
			this.showCropper = false
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
				this.loading = false
				this.emojiAvatar = ''
				this.backgroundColor = ''
			} catch (e) {
				showError(t('spreed', 'Error saving profile picture'))
				this.loading = false
			}
		},

		savePictureAvatar() {
			const canvasData = this.$refs.cropper.getCroppedCanvas()
			const scaleFactor = canvasData.width > 512 ? 512 / canvasData.width : 1
			this.$refs.cropper.scale(scaleFactor, scaleFactor).getCroppedCanvas().toBlob(async (blob) => {
				if (blob === null) {
					showError(t('spreed', 'Error cropping profile picture'))
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
					this.loading = false
				} catch (e) {
					showError(t('spreed', 'Error saving profile picture'))
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
				this.loading = false
			} catch (e) {
				showError(t('spreed', 'Error removing profile picture'))
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
		margin: 0 auto;
		display: flex;
		flex-direction: column;
		justify-content: center;
		align-items: center;
		gap: 16px 0;
		width: 300px;
	}

	&__warning {
		color: var(--color-text-lighter);
	}

	&__preview {
		display: flex;
		justify-content: center;
		align-items: center;
		width: 180px;
		height: 180px;

		&-emoji {
			display: flex;
			justify-content: center;
			align-items: center;
			width: 100%;
			height: 100%;
			padding-bottom: 6px;
			border-radius: 100%;
			background-color: var(--color-background-darker);
			font-size: 575%;
			line-height: 100%;
		}
	}

	&__buttons {
		display: flex;
		gap: 0 10px;
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
