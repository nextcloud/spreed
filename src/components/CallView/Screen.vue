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
	<div v-show="sharedData.screenVisible" :id="screenContainerId" class="screenContainer">
		<video v-show="(localMediaModel && localMediaModel.attributes.localScreen) || (callParticipantModel && callParticipantModel.attributes.screen)" ref="screen" />
		<div class="nameIndicator">
			{{ name }}
		</div>
	</div>
</template>

<script>
import attachMediaStream from 'attachmediastream'

export default {

	name: 'Screen',

	props: {
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
	},

	computed: {

		screenContainerId() {
			if (this.localMediaModel) {
				return 'localScreenContainer'
			}

			return 'container_' + this.callParticipantModel.attributes.peerId + '_screen_incoming'
		},

		name() {
			if (this.localMediaModel) {
				return t('spreed', 'Your screen')
			}

			if (this.callParticipantModel.attributes.name) {
				return t('spreed', "{participantName}'s screen", { participantName: this.callParticipantModel.attributes.name })
			}

			return t('spreed', "Guest's screen")
		},

	},

	watch: {

		'localMediaModel.attributes.localScreen': function(localScreen) {
			this._setScreen(localScreen)
		},

		'callParticipantModel.attributes.screen': function(screen) {
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

			attachMediaStream(screen, this.$refs.screen)
		},

	},

}
</script>
