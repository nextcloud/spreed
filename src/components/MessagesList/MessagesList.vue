<!--
  - @copyright Copyright (c) 2019 Marco Ambrosini <marcoambrosini@pm.me>
  -
  - @author Marco Ambrosini <marcoambrosini@pm.me>
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
  - MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
  - GNU Affero General Public License for more details.
  -
  - You should have received a copy of the GNU Affero General Public License
  - along with this program. If not, see <http://www.gnu.org/licenses/>.
-->
<docs>

This component is a wrapper for the list of messages. It's main purpose it to
get the messagesList array and loop through the list to generate the messages.
In order not to render each and every messages that is in the store, we use
the Vue virtual scroll list component, whose docs you can find [here.](https://github.com/tangbc/vue-virtual-scroll-list)

</docs>

<template>
	<virtual-list :size="40" :remain="8" :variable="true"
		class="scroller">
		<MessagesGroup
			v-for="item of messagesGroupedByAuthor"
			:key="item[0].id"
			:style="{ height: item.height + 'px' }"
			v-bind="item"
			:messages="item"
			@deleteMessage="handleDeleteMessage" />
	</virtual-list>
</template>

<script>
import virtualList from 'vue-virtual-scroll-list'
import MessagesGroup from './MessagesGroup/MessagesGroup'
import { fetchMessages, lookForNewMessges } from '../../services/messagesService'

export default {
	name: 'MessagesList',
	components: {
		MessagesGroup,
		virtualList
	},

	props: {
		/**
		 * The conversation token.
		 */
		token: {
			type: String,
			required: true
		}
	},

	data: function() {
		return {
			/**
			 * Keeps track of the state of the component in order to trigger the scroll to
			 * bottom.
			 */
			isInitiated: false
		}
	},

	computed: {
		/**
		 * Gets the messages array. We need this because the DynamicScroller needs an array to
		 * loop through.
		 *
		 * @returns {array}
		 */
		messagesList() {
			return this.$store.getters.messagesList(this.token)
		},
		/**
		 * Gets the messages object, which is structured so that the key of each message element
		 * corresponds to the id of the message, and makes it easy and efficient to access the
		 * individual message object.
		 *
		 * @returns {object}
		 */
		messages() {
			return this.$store.getters.messages(this.token)
		},
		/**
		 * Creates an array of messages grouped in nested arrays by same autor.
		 * @returns {array}
		 */
		messagesGroupedByAuthor() {
			let groups = []
			let currentAuthor = ''
			for (let message of this.messagesList) {
				if (message.actorId !== currentAuthor) {
					groups.push([message])
					currentAuthor = message.actorId
				} else {
					groups[groups.length - 1].push(message)
				}
			}
			return groups
		}
	},

	watch: {
		token: function() {
			this.onTokenChange()
		}
	},

	/**
	 * Fetches the messages when the MessageList is mounted for the
	 * first time. The router mounts this component only if the token
	 * is passed in so there's no need to check the token prop.
	 */
	beforeMount() {
		this.onTokenChange()
	},

	beforeUpdate() {
		/**
		 * If the component is not initiated, scroll to the bottom of the message list.
		 */
		if (!this.isInitiated) {
			this.scrollToBottom()
			this.isInitiated = true
		}
	},

	methods: {

		/**
		 * Fetches the messaes of a conversation given the
		 * conversation token.
		 */
		async onTokenChange() {
			this.isInitiated = false
			const messages = await fetchMessages(this.token)
			// Process each messages and adds it to the store
			messages.data.ocs.data.forEach(message => {
				this.$store.dispatch('processMessage', message)
			})
			// After loading the old messages to the store, we start looking for new mwssages.
			this.getNewMessages()
		},

		/**
		 * Creates a long polling request for a new message.
		 */
		async getNewMessages() {
			const lastKnownMessageId = this.messagesList[this.messagesList.length - 1].id
			const messages = await lookForNewMessges(this.token, lastKnownMessageId)
			// If there are no new messages, the variable messages will be undefined.
			if (messages !== undefined) {
				// Process each messages and adds it to the store
				messages.data.ocs.data.forEach(message => {
					this.$store.dispatch('processMessage', message)
				})
				this.scrollToBottom()
			}
			/**
			 * This method recursively call itself after a response, so we're always
			 * looking for new messages.
			 */
			this.getNewMessages()
		},

		/**
		 * Dispatches the deleteMessages action.
		 * @param {object} event The deleteMessage event emitted by the Message component.
		 */
		handleDeleteMessage(event) {
			this.$store.dispatch('deleteMessage', event.message)
		},
		/**
		 * Scrolls to the bottom of the list.
		 */
		scrollToBottom() {
			this.$nextTick(function() {
				document.querySelector('.scroller').scrollTop = document.querySelector('.scroller').scrollHeight
			})
		}

	}
}
</script>

<style lang="scss" scoped>
.scroller {
	flex: 1 0;
}
</style>
