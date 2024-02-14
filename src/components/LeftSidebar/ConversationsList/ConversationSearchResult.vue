<!--
  - @copyright Copyright (c) 2024 Maksim Sukharev <antreesy.web@gmail.com>
  -
  - @author Joas Schilling <coding@schilljs.com>
  - @author Maksim Sukharev <antreesy.web@gmail.com>
  -
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
-->

<template>
	<NcListItem :key="item.token"
		:name="item.displayName"
		:title="item.displayName"
		:active="item.token === selectedRoom"
		:bold="!!item.unreadMessages"
		:counter-number="item.unreadMessages"
		:counter-type="counterType"
		@click="onClick">
		<template #icon>
			<ConversationIcon :item="item" :hide-favorite="!item?.attendeeId" :hide-call="!item?.attendeeId" />
		</template>
		<template v-if="conversationInformation" #subname>
			{{ conversationInformation }}
		</template>
	</NcListItem>
</template>

<script>
import { inject } from 'vue'

import NcListItem from '@nextcloud/vue/dist/Components/NcListItem.js'

import ConversationIcon from './../../ConversationIcon.vue'

import { CONVERSATION, ATTENDEE } from '../../../constants.js'

export default {
	name: 'ConversationSearchResult',

	components: {
		ConversationIcon,
		NcListItem,
	},

	props: {
		item: {
			type: Object,
			default() {
				return {
					token: '',
					participants: [],
					participantType: 0,
					unreadMessages: 0,
					unreadMention: false,
					objectType: '',
					type: 0,
					displayName: '',
					isFavorite: false,
					notificationLevel: 0,
					lastMessage: {},
				}
			},
		},
	},

	emits: ['click'],

	setup() {
		const selectedRoom = inject('selectedRoom', null)

		return {
			selectedRoom,
		}
	},

	computed: {
		counterType() {
			if (this.item.unreadMentionDirect || (this.item.unreadMessages !== 0 && (
				this.item.type === CONVERSATION.TYPE.ONE_TO_ONE || this.item.type === CONVERSATION.TYPE.ONE_TO_ONE_FORMER
			))) {
				return 'highlighted'
			} else if (this.item.unreadMention) {
				return 'outlined'
			} else {
				return ''
			}
		},

		conversationInformation() {
			if (!Object.keys(Object(this.item?.lastMessage)).length) {
				return ''
			}

			if (this.shortLastChatMessageAuthor === '') {
				return this.simpleLastChatMessage
			}

			if (this.item.lastMessage.actorId === this.item.actorId) {
				return t('spreed', 'You: {lastMessage}', {
					lastMessage: this.simpleLastChatMessage,
				}, undefined, {
					escape: false,
					sanitize: false,
				})
			}

			if (this.item.type === CONVERSATION.TYPE.ONE_TO_ONE
				|| this.item.type === CONVERSATION.TYPE.ONE_TO_ONE_FORMER
				|| this.item.type === CONVERSATION.TYPE.CHANGELOG) {
				return this.simpleLastChatMessage
			}

			return t('spreed', '{actor}: {lastMessage}', {
				actor: this.shortLastChatMessageAuthor,
				lastMessage: this.simpleLastChatMessage,
			}, undefined, {
				escape: false,
				sanitize: false,
			})
		},

		/**
		 * This is a simplified version of the last chat message.
		 * Parameters are parsed without markup (just replaced with the name),
		 * e.g. no avatars on mentions.
		 *
		 * @return {string} A simple message to show below the conversation name
		 */
		simpleLastChatMessage() {
			if (!Object.keys(this.item.lastMessage).length) {
				return ''
			}

			const params = this.item.lastMessage.messageParameters
			let subtitle = this.item.lastMessage.message.trim()

			// We don't really use rich objects in the subtitle, instead we fall back to the name of the item
			Object.keys(params).forEach((parameterKey) => {
				subtitle = subtitle.replace('{' + parameterKey + '}', params[parameterKey].name)
			})

			return subtitle
		},

		/**
		 * @return {string} Part of the name until the first space
		 */
		shortLastChatMessageAuthor() {
			if (!Object.keys(this.item.lastMessage).length
				|| this.item.lastMessage.systemMessage.length) {
				return ''
			}

			let author = this.item.lastMessage.actorDisplayName.trim()
			const spacePosition = author.indexOf(' ')
			if (spacePosition !== -1) {
				author = author.substring(0, spacePosition)
			}

			if (author.length === 0 && this.item.lastMessage.actorType === ATTENDEE.ACTOR_TYPE.GUESTS) {
				return t('spreed', 'Guest')
			}

			return author
		},
	},

	methods: {
		onClick() {
			this.$emit('click', this.item)
		},
	},
}
</script>
