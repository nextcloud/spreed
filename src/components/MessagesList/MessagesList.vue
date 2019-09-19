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

<template>
	<DynamicScroller
		:items="messagesList"
		:min-item-size="60"
		class="scroller">
		<template v-slot="{ item, index, active }">
			<DynamicScrollerItem
				:item="item"
				:active="active"
				:size-dependencies="[
					item.messageText,
				]"
				:data-index="item.id">
				<Message v-bind="item" :message="item" @deleteMessage="handleDeleteMessage">
					<MessageBody v-bind="item">
						<MessageBody v-if="item.parent" v-bind="messages[item.parent]" />
					</MessageBody>
				</Message>
			</DynamicScrollerItem>
		</template>
	</DynamicScroller>
</template>

<script>
import { DynamicScroller, DynamicScrollerItem } from 'vue-virtual-scroller/dist/vue-virtual-scroller.umd.js'
import 'vue-virtual-scroller/dist/vue-virtual-scroller.css'
import Message from './Message/Message'
import MessageBody from './Message/MessageBody'
import { fetchMessages } from '../../services/messagesService'

export default {
	name: 'MessagesList',
	components: {
		DynamicScroller,
		DynamicScrollerItem,
		Message,
		MessageBody
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
			isInitiated: false
		}
	},
	computed: {
		/**
		 * Gets the messages array.
		 *
		 * @returns {Array}
		 */
		messagesList() {
			return this.$store.getters.messagesList(this.token)
		},
		/**
		 * Gets the messages object.
		 *
		 * @returns {Object}
		 */
		messages() {
			return this.$store.getters.messages(this.token)
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
		if (!this.isInitiated) {

			// Scrolls to the bottom of the message list.

			this.$nextTick(function() {
				document.querySelector('.scroller').scrollTop = document.querySelector('.scroller').scrollHeight
			})
			this.isInitiated = true
		}

	},
	methods: {
		async onTokenChange() {
			this.isInitiated = false
			/**
			 * Fetches the messaes of a conversation given the
			 * conversation token.
			 */
			const messages = await fetchMessages(this.token)
			messages.data.ocs.data.forEach(message => {
				// Process each messages and adds it to the store
				this.$store.dispatch('processMessage', message)
			})
		},
		scrollToEnd: function() {
			this.$el.scrollTop = this.$el.scrollHeight
		},
		handleDeleteMessage(event) {
			this.$store.dispatch('deleteMessage', event.message)
		}
	}
}
</script>

<style lang="scss" scoped>
.scroller {
	flex: 1 0;
}
</style>
