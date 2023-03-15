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
				<ConversationIcon v-if="!loading"
					:item="conversation"
					:is-big="true"
					:disable-menu="true" />
				<div v-else class="icon-loading" />
			</div>
			<div class="avatar__buttons">
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
			<span>{{ t('spreed', 'png or jpg, max. 20 MB') }}</span>
			<input :id="inputId"
				ref="input"
				type="file"
				:accept="validMimeTypes.join(',')"
				@change="onChange">
		</div>

		<!-- Use v-show to ensure early cropper ref availability -->
		<div v-show="showCropper" class="avatar__container">
			<VueCropper ref="cropper"
				class="avatar__cropper"
				v-bind="cropperOptions" />
			<div class="avatar__cropper-buttons">
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

import axios from '@nextcloud/axios'
import { getFilePickerBuilder, showError } from '@nextcloud/dialogs'
import { generateUrl } from '@nextcloud/router'

// import NcAvatar from '@nextcloud/vue/dist/Components/NcAvatar.js'
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'

import ConversationIcon from '../ConversationIcon.vue'

// eslint-disable-next-line n/no-extraneous-import
import 'cropperjs/dist/cropper.css'

import Upload from 'vue-material-design-icons/Upload.vue'
import Folder from 'vue-material-design-icons/Folder.vue'
import Delete from 'vue-material-design-icons/Delete.vue'

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
		Delete,
		Folder,
		// NcAvatar,
		NcButton,
		Upload,
		VueCropper,
		ConversationIcon,
	},

	props: {
		conversation: {
			type: Object,
			required: true,
		},
	},

	data() {
		return {
			// Todo: get real value here
			showCropper: false,
			loading: false,
			validMimeTypes: VALID_MIME_TYPES,
			cropperOptions: {
				aspectRatio: 1 / 1,
				viewMode: 1,
				guides: false,
				center: false,
				highlight: false,
				autoCropArea: 1,
				minContainerWidth: 300,
				minContainerHeight: 300,
			},
		}
	},

	computed: {
		inputId() {
			return `account-property-${this.conversation.displayName}`
		},

		hasAvatar() {
			return !!this.conversation.avatarVersion
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
				showError(t('spreed', 'Please select a valid png or jpg file'))
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
					const tempAvatar = generateUrl('/avatar/tmp') + '?requesttoken=' + encodeURIComponent(OC.requestToken) + '#' + Math.floor(Math.random() * 1000)
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

		saveAvatar() {
			this.showCropper = false
			this.loading = true

			this.$refs.cropper.getCroppedCanvas().toBlob(async (blob) => {
				if (blob === null) {
					showError(t('spreed', 'Error cropping profile picture'))
					this.cancel()
					return
				}

				const formData = new FormData()
				formData.append('file', blob)

				try {
					await this.$store.dispatch('setConversationPictureAction', {
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
				await this.$store.dispatch('deleteConversationPictureAction', {
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

		span {
			color: var(--color-text-lighter);
		}
	}

	&__preview {
		display: flex;
		justify-content: center;
		align-items: center;
		width: 180px;
		height: 180px;
	}

	&__buttons {
		display: flex;
		gap: 0 10px;
	}

	&__cropper {
		width: 300px;
		height: 300px;
		overflow: hidden;

		&-buttons {
			width: 100%;
			display: flex;
			justify-content: space-between;
		}

		&::v-deep .cropper-view-box {
			border-radius: 50%;
		}
	}
}

input[type="file"] {
	display: none;
}

</style>
