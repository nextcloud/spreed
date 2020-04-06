<!--
  - @copyright Copyright (c) 2020, Daniel Calviño Sánchez <danxuliu@gmail.com>
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
	<div class="emptycontent">
		<div :class="iconClass" />
		<h2>
			{{ title }}
		</h2>
		<p v-if="message" class="emptycontent-additional">
			{{ message }}
		</p>
		<button v-if="showLink"
			class="primary"
			@click.stop.prevent="copyLinkToConversation">
			{{ t('spreed', 'Copy link') }}
		</button>
	</div>
</template>

<script>
import { generateUrl } from '@nextcloud/router'
import { CONVERSATION, PARTICIPANT } from '../../constants'

export default {

	name: 'EmptyCallView',

	computed: {

		token() {
			return this.$store.getters.getToken()
		},

		conversation() {
			return this.$store.getters.conversation(this.token)
		},

		isGroupConversation() {
			return this.conversation && this.conversation.type === CONVERSATION.TYPE.GROUP
		},

		isPublicConversation() {
			return this.conversation && this.conversation.type === CONVERSATION.TYPE.PUBLIC
		},

		isPasswordRequestConversation() {
			return this.conversation && this.conversation.objectType === 'share:password'
		},

		isFileConversation() {
			return this.conversation && this.conversation.objectType === 'file'
		},

		canInviteOthers() {
			return this.conversation && (
				this.conversation.participantType === PARTICIPANT.TYPE.OWNER
				|| this.conversation.participantType === PARTICIPANT.TYPE.MODERATOR)
		},

		canInviteOthersInPublicConversations() {
			return this.canInviteOthers
				|| (this.conversation && this.conversation.participantType === PARTICIPANT.TYPE.GUEST_MODERATOR)
		},

		iconClass() {
			return {
				'icon-public': this.isPublicConversation,
				'icon-contacts': !this.isPublicConversation,
			}
		},

		title() {
			return t('spreed', 'Waiting for others to join the call …')
		},

		message() {
			if (this.isPasswordRequestConversation || this.isFileConversation) {
				return ''
			}

			if (!this.isGroupConversation && !this.isPublicConversation) {
				return ''
			}

			if (this.isGroupConversation && !this.canInviteOthers) {
				return ''
			}

			if (this.isGroupConversation) {
				return t('spreed', 'You can invite others in the participant tab of the sidebar')
			}

			if (this.isPublicConversation && this.canInviteOthersInPublicConversations) {
				return t('spreed', 'You can invite others in the participant tab of the sidebar or share this link to invite others!')
			}

			return t('spreed', 'Share this link to invite others!')
		},

		showLink() {
			return this.isPublicConversation && !this.isPasswordRequestConversation && !this.isFileConversation
		},

		linkToConversation() {
			return window.location.protocol + '//' + window.location.host + generateUrl('/call/' + this.token)
		},

	},

	methods: {
		async copyLinkToConversation() {
			try {
				await this.$copyText(this.linkToConversation)
				OCP.Toast.success(t('spreed', 'Conversation link copied to clipboard.'))
			} catch (error) {
				OCP.Toast.error(t('spreed', 'The link could not be copied.'))
			}
		},
	},

}
</script>
