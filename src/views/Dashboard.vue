<!--
  - @copyright Copyright (c) 2020 Julius Härtl <jus@bitgrid.net>
  -
  - @author Julius Härtl <jus@bitgrid.net>
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
  -
  -->

<template>
	<NcDashboardWidget id="talk-panel"
		:items="roomOptions"
		:show-more-url="''"
		:loading="loading"
		:show-items-and-empty-content="!hasImportantConversations"
		:half-empty-content-message="t('spreed', 'No unread mentions')">
		<template #default="{ item }">
			<NcDashboardWidgetItem :target-url="getItemTargetUrl(item)"
				:main-text="getMainText(item)"
				:sub-text="getSubText(item)"
				:item="item">
				<template #avatar>
					<ConversationIcon :item="item" :hide-call="false" />
				</template>
			</NcDashboardWidgetItem>
		</template>
		<template #empty-content>
			<NcEmptyContent :description="t('spreed', 'Say hi to your friends and colleagues!')">
				<template #icon>
					<span class="icon icon-talk" />
				</template>
				<template #action>
					<NcButton class="button-start-conversation"
						type="secondary"
						@click="clickStartNew">
						{{ t('spreed', 'Start a conversation') }}
					</NcButton>
				</template>
			</NcEmptyContent>
		</template>
	</NcDashboardWidget>
</template>

<script>
import axios from '@nextcloud/axios'
import { generateOcsUrl, generateUrl } from '@nextcloud/router'

import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcDashboardWidget from '@nextcloud/vue/dist/Components/NcDashboardWidget.js'
import NcDashboardWidgetItem from '@nextcloud/vue/dist/Components/NcDashboardWidgetItem.js'
import NcEmptyContent from '@nextcloud/vue/dist/Components/NcEmptyContent.js'

import ConversationIcon from './../components/ConversationIcon.vue'

import { CONVERSATION } from '../constants.js'

const ROOM_POLLING_INTERVAL = 30

const propertySort = (properties) => (a, b) => properties.map(obj => {
	let dir = 1
	if (obj[0] === '-') {
		dir = -1
		obj = obj.substring(1)
	}
	return a[obj] > b[obj] ? dir : a[obj] < b[obj] ? -(dir) : 0
}).reduce((p, n) => p || n, 0)

export default {
	name: 'Dashboard',

	components: {
		NcDashboardWidget,
		NcDashboardWidgetItem,
		NcButton,
		ConversationIcon,
		NcEmptyContent,
	},

	data() {
		return {
			roomOptions: [],
			hasImportantConversations: false,
			loading: true,
			windowVisibility: true,
		}
	},

	computed: {
		callLink() {
			return (conversation) => {
				return generateUrl('call/' + conversation.token)
			}
		},

		/**
		 * This is a simplified version of the last chat message.
		 * Parameters are parsed without markup (just replaced with the name),
		 * e.g. no avatars on mentions.
		 *
		 * @return {string} A simple message to show below the conversation name
		 */
		simpleLastChatMessage() {
			return (lastChatMessage) => {
				if (!Object.keys(lastChatMessage).length) {
					return ''
				}

				const params = lastChatMessage.messageParameters
				let subtitle = lastChatMessage.message.trim()

				// We don't really use rich objects in the subtitle, instead we fall back to the name of the item
				Object.keys(params).forEach((parameterKey) => {
					subtitle = subtitle.replace('{' + parameterKey + '}', params[parameterKey].name)
				})

				return subtitle
			}
		},

		getItemTargetUrl() {
			return (conversation) => {
				return generateUrl(`call/${conversation.token}`)
			}
		},

		getMainText() {
			return (conversation) => {
				return conversation.displayName
			}
		},

		getSubText() {
			return (conversation) => {
				if (conversation.hasCall) {
					return t('spreed', 'Call in progress')
				}

				if (conversation.unreadMention) {
					return t('spreed', 'You were mentioned')
				}

				return this.simpleLastChatMessage(conversation.lastMessage)
			}
		},
	},

	watch: {
		windowVisibility(newValue) {
			if (newValue) {
				this.fetchRooms()
			}
		},
	},

	beforeDestroy() {
		document.removeEventListener('visibilitychange', this.changeWindowVisibility)
	},

	beforeMount() {
		this.fetchRooms()
		setInterval(this.fetchRooms, ROOM_POLLING_INTERVAL * 1000)
		document.addEventListener('visibilitychange', this.changeWindowVisibility)
	},

	methods: {
		fetchRooms() {
			if (!this.windowVisibility) {
				// Dashboard is not visible, so don't update the room list
				return
			}

			axios.get(generateOcsUrl('apps/spreed/api/v4/room')).then((response) => {
				const allRooms = response.data.ocs.data
				// filter out breakout rooms
				const rooms = allRooms.filter((conversation) => conversation.objectType !== CONVERSATION.OBJECT_TYPE.BREAKOUT_ROOM)
				const importantRooms = rooms.filter((conversation) => {
					return conversation.hasCall
						|| conversation.unreadMention
						|| (conversation.unreadMessages > 0 && (conversation.type === CONVERSATION.TYPE.ONE_TO_ONE || conversation.type === CONVERSATION.TYPE.ONE_TO_ONE_FORMER))
				})

				if (importantRooms.length) {
					// FIXME unread 1-1 conversations are not sorted like unread mentions in group chats
					importantRooms.sort(propertySort(['-hasCall', '-unreadMention', '-lastActivity']))
					this.roomOptions = importantRooms.slice(0, 7)
					this.hasImportantConversations = true
				} else {
					this.roomOptions = rooms.sort(propertySort(['-isFavorite', '-lastActivity'])).slice(0, 5)
					this.hasImportantConversations = false
				}

				this.loading = false
			})
		},

		changeWindowVisibility() {
			this.windowVisibility = !document.hidden
		},

		clickStartNew() {
			window.location = generateUrl('/apps/spreed')
		},
	},
}
</script>

<style lang="scss" scoped>
	:deep(.item-list__entry) {
		position: relative;
	}

	.empty-content {
		text-align: center;
		margin-top: 5vh;

		.icon-talk {
			width: 64px;
			height: 64px;
			background-size: 64px;
		}

		&.half-screen {
			margin-top: 0;
			margin-bottom: 2vh;
		}
	}

	.button-start-conversation {
		margin: 0 auto;
		margin-top: 3px;
	}
</style>
