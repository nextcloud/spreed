<!--
  - @copyright Copyright (c) 2019, Daniel Calviño Sánchez (danxuliu@gmail.com)
  -
  - @license GNU AGPL version 3 or any later version
  -
  - This program is free software: you can redistribute it and/or modify
  - it under the terms of the GNU Affero General Public License as
  - published by the Free Software Foundation, either version 3 of the
  - License, or (at your option) any later version.
  -
  - This program is distributed in the hope that it will be useful,
  - but WITHOUT ANY WARRANTY; without even the implied warranty of
  - MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  - GNU Affero General Public License for more details.
  -
  - You should have received a copy of the GNU Affero General Public License
  - along with this program. If not, see <http://www.gnu.org/licenses/>.
  -
  -->

<template>
	<div :id="screenContainerId" class="screenContainer">
		<video v-show="(localMediaModel && localMediaModel.attributes.localScreen) || (callParticipantModel && callParticipantModel.attributes.screen)"
			ref="screen"
			:disablePictureInPicture="!isBig ? 'true' : 'false'"
			class="screen"
			:class="screenClass" />
		<VideoBottomBar v-if="isBig"
			v-bind="$props"
			:is-big="true"
			:is-screen="true"
			:model="model"
			:participant-name="remoteParticipantName" />
	</div>
</template>

<script>
import attachMediaStream from 'attachmediastream/attachmediastream.bundle.js'
import Hex from 'crypto-js/enc-hex.js'
import SHA1 from 'crypto-js/sha1.js'

import VideoBottomBar from './VideoBottomBar.vue'

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
			return Hex.stringify(SHA1(this.callParticipantModel.attributes.peerId))
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
				remoteParticipantName = this.$store.getters.getGuestName(
					this.$store.getters.getToken(),
					this.remoteSessionHash,
				)
			}

			return remoteParticipantName
		},
		screenClass() {
			if (this.isBig) {
				return 'screen--fit'
			} else {
				return 'screen--fill'
			}
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
}

</style>
