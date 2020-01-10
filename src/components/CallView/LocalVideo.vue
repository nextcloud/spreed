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
	<div id="localVideoContainer" class="videoContainer videoView" :class="{ speaking: localMediaModel.attributes.speaking }">
		<video v-show="localMediaModel.attributes.videoEnabled" id="localVideo" ref="video" />
		<div v-if="!localMediaModel.attributes.videoEnabled" class="avatar-container">
			<Avatar v-if="userId"
				:size="avatarSize"
				:disable-menu="true"
				:disable-tooltip="true"
				:user="userId"
				:display-name="displayName" />
			<Avatar v-else
				:size="avatarSize"
				:disable-menu="true"
				:disable-tooltip="true"
				:display-name="guestName" />
		</div>
		<LocalMediaControls ref="localMediaControls"
			:model="localMediaModel"
			:local-call-participant-model="localCallParticipantModel"
			:screen-sharing-button-hidden="useConstrainedLayout"
			@switchScreenToId="$emit('switchScreenToId', $event)" />
	</div>
</template>

<script>
import attachMediaStream from 'attachmediastream'
import Avatar from '@nextcloud/vue/dist/Components/Avatar'
import LocalMediaControls from './LocalMediaControls'

export default {

	name: 'LocalVideo',

	components: {
		Avatar,
		LocalMediaControls,
	},

	props: {
		localMediaModel: {
			type: Object,
			required: true,
		},
		localCallParticipantModel: {
			type: Object,
			required: true,
		},
		useConstrainedLayout: {
			type: Boolean,
			default: false,
		},
	},

	computed: {

		userId() {
			return this.$store.getters.getUserId()
		},

		displayName() {
			return this.$store.getters.getDisplayName()
		},

		guestName() {
			return this.localCallParticipantModel.attributes.guestName || localStorage.getItem('nick') || '?'
		},

		avatarSize() {
			return this.useConstrainedLayout ? 64 : 128
		},

	},

	watch: {

		'localMediaModel.attributes.localStream': function(localStream) {
			this._setLocalStream(localStream)
		},

	},

	mounted() {
		// Set initial state
		this._setLocalStream(this.localMediaModel.attributes.localStream)
	},

	methods: {

		_setLocalStream(localStream) {
			if (!localStream) {
				// Do not clear the srcObject of the video element, just leave
				// the previous stream as a frozen image.

				return
			}

			const options = {
				autoplay: true,
				mirror: true,
				muted: true,
			}
			attachMediaStream(localStream, this.$refs.video, options)
		},

	},

}
</script>
