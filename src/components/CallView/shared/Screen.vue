<!--
  - SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div :id="screenContainerId" class="screenContainer">
		<video v-show="(localMediaModel && localMediaModel.attributes.localScreen) || (callParticipantModel && callParticipantModel.attributes.screen)"
			ref="screen"
			:disablePictureInPicture="!isBig ? 'true' : 'false'"
			class="screen"
			:class="screenClass" />
		<VideoBottomBar v-if="isBig"
			:token="token"
			:shared-data="sharedData"
			is-big
			is-screen
			:model="model"
			:participant-name="remoteParticipantName" />
	</div>
</template>

<script>
import Hex from 'crypto-js/enc-hex.js'
import SHA1 from 'crypto-js/sha1.js'
import panzoom from 'panzoom'
import { computed, ref, onMounted, onBeforeUnmount } from 'vue'

import { t } from '@nextcloud/l10n'

import VideoBottomBar from './VideoBottomBar.vue'

import { useGuestNameStore } from '../../../stores/guestName.js'
import attachMediaStream from '../../../utils/attachmediastream.js'

export default {

	name: 'Screen',

	components: {
		VideoBottomBar,
	},

	props: {
		token: {
			type: String,
			required: true,
		},
		localMediaModel: {
			type: Object,
			default: null,
		},
		callParticipantModel: {
			type: Object,
			default: null,
		},
		sharedData: {
			type: Object,
			required: true,
		},
		isBig: {
			type: Boolean,
			default: false,
		},
	},

	setup(props) {
		const guestNameStore = useGuestNameStore()

		const screen = ref(null)
		const instance = ref(null)
		const instanceTransform = ref({ x: 0, y: 0, scale: 1 })
		const instanceGrabbing = ref(false)

		const screenClass = computed(() => {
			if (!props.isBig) {
				return ['screen--fill']
			} else {
				return [
					'screen--fit',
					instanceTransform.value.scale === 1
						? 'screen--magnify'
						: (instanceGrabbing.value ? 'screen--grabbing' : 'screen--grab'),
				]
			}
		})

		onMounted(() => {
			if (props.isBig) {
				instance.value = panzoom(screen.value, {
					minZoom: 1,
					maxZoom: 8,
					bounds: true,
					boundsPadding: 1,
				})
				instance.value.on('zoom', (instance) => {
					instanceTransform.value = instance.getTransform()
				})
				instance.value.on('panstart', () => {
					instanceGrabbing.value = true
				})
				instance.value.on('panend', () => {
					instanceGrabbing.value = false
				})
			}
		})
		onBeforeUnmount(() => {
			instance.value?.dispose()
		})

		return {
			guestNameStore,
			screen,
			screenClass,
		}
	},

	computed: {
		model() {
			if (this.callParticipantModel) {
				return this.callParticipantModel
			}
			return this.localMediaModel
		},

		screenContainerId() {
			if (this.localMediaModel) {
				return 'localScreenContainer'
			}

			return 'container_' + this.callParticipantModel.attributes.peerId + '_screen_incoming'
		},

		remoteSessionHash() {
			if (!this.callParticipantModel) {
				return null
			}

			return Hex.stringify(SHA1(this.callParticipantModel.attributes.nextcloudSessionId))
		},

		remoteParticipantName() {
			if (!this.callParticipantModel) {
				return t('spreed', 'You')
			}

			let remoteParticipantName = this.callParticipantModel.attributes.name

			// The name is undefined and not shown until a connection is made
			// for registered users, so do not fall back to the guest name in
			// the store either until the connection was made.
			if (!this.callParticipantModel.attributes.userId && !remoteParticipantName && remoteParticipantName !== undefined) {
				remoteParticipantName = this.guestNameStore.getGuestName(
					this.$store.getters.getToken(),
					this.remoteSessionHash,
				)
			}

			return remoteParticipantName
		},
	},

	watch: {

		'localMediaModel.attributes.localScreen'(localScreen) {
			this._setScreen(localScreen)
		},

		'callParticipantModel.attributes.screen'(screen) {
			this._setScreen(screen)
		},

	},

	mounted() {
		// Set initial state
		if (this.localMediaModel) {
			this._setScreen(this.localMediaModel.attributes.localScreen)
		} else {
			this._setScreen(this.callParticipantModel.attributes.screen)
		}
	},

	methods: {
		t,

		_setScreen(screen) {
			if (!screen) {
				this.$refs.screen.srcObject = null

				return
			}

			// The audio is played using an audio element in the model to be
			// able to hear it even if there is no view for it.
			attachMediaStream(screen, this.$refs.screen)

			this.$refs.screen.muted = true
		},

	},

}
</script>

<style lang="scss" scoped>
/* stylelint-disable csstools/use-logical */
.screenContainer {
	width: 100%;
	height: 100%;
}

.screen {
	width: 100%;
	height: 100%;
	position: absolute;
	top: 0;
	left: 0;
	&--fit {
		object-fit: contain;
	}
	&--fill {
		object-fit: cover;
	}
	&--magnify {
		cursor: zoom-in;
	}
	&--grab {
		cursor: grab;
	}
	&--grabbing {
		cursor: grabbing;
	}
}

</style>
