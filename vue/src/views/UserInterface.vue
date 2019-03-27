<!--
 - @copyright Copyright (c) 2019 Joas Schilling <coding@schilljs.com>
 -
 - @author Joas Schilling <coding@schilljs.com>
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
	<div id="content">
		<div id="app-navigation">
			<app-content app-name="settings" :class="{ 'icon-loading': loading }">
				<app-navigation>
					<!--<app-navigation-new button-id="new-user-button" :text="t('settings','New user')" button-class="icon-add" />-->
					<ul id="spreedme-room-list" class="with-icon">
						<app-navigation-item v-for="item in menu" :key="item.key" :item="item" />
					</ul>
				</app-navigation>
			</app-content>
		</div>
		<router-view />
	</div>
</template>

<script>

import {
	AppContent,
	AppNavigation,
	// AppNavigationNew,
	AppNavigationItem
} from 'nextcloud-vue'
import {
	Conversation,
	Participant
} from '../constants'

export default {
	name: 'UserInterface',

	components: {
		AppContent,
		AppNavigation,
		// AppNavigationNew,
		AppNavigationItem
	},

	data() {
		return {
			loading: {
				type: Boolean,
				default: true
			}
		}
	},

	computed: {
		activeRoomToken() {
			return this.$route.params.token
		},
		filters() {
			return this.$store.getters.getConversations
		},
		menu() {
			const conversations = this.$store.getters.getConversations
			const items = []

			Object.keys(conversations).forEach(token => {
				const c = conversations[token]
				let icon = 'icon icon-people'
				if (c.objectType === 'file') {
					icon = 'icon icon-file'
				} else if (c.objectType === 'share:password') {
					icon = 'icon icon-password'
				} else if (c.type === Conversation.TYPE_CHANGELOG) {
					icon = 'icon icon-changelog'
				} else if (c.type === Conversation.TYPE_GROUP) {
					icon = 'icon icon-contacts'
				} else if (c.type === Conversation.TYPE_PUBLIC) {
					icon = 'icon icon-public'
				}

				let actions = []
				if (c.participantType !== Participant.TYPE_USER_SELFJOINED) {
					if (!c.isFavorite) {
						actions.push({
							icon: 'icon-starred',
							text: t('spreed', 'Add to favorites'),
							action: function() {
								alert('add')
							}
						})
					} else {
						actions.push({
							icon: 'icon-star-dark',
							text: t('spreed', 'Remove from favorites'),
							action: function() {
								alert('remove')
							}
						})
					}
				}
				actions.push({
					icon: 'icon-clippy',
					text: t('spreed', 'Copy link'),
					action: function() {
						alert('copy')
					}
				})

				// FIXME separator support missing
				actions.push({
					text: 'divider',
					caption: true
				})

				actions.push({
					icon: 'icon-sound',
					text: t('spreed', 'Always notify'),
					active: c.notificationLevel === Participant.NOTIFY_ALWAYS,
					action: function() {
						alert('remove')
					}
				})
				actions.push({
					icon: 'icon-user',
					text: t('spreed', 'Notify on @-mention'),
					active: c.notificationLevel === Participant.NOTIFY_MENTION,
					action: function() {
						alert('remove')
					}
				})
				actions.push({
					icon: 'icon-sound-off',
					text: t('spreed', 'Never notify'),
					active: c.notificationLevel === Participant.NOTIFY_NEVER,
					action: function() {
						alert('remove')
					}
				})

				// FIXME separator support missing
				actions.push({
					text: 'divider',
					caption: true
				})

				const isDeletable = c.type !== 1
					&& (c.participantType === Participant.TYPE_OWNER || c.participantType === Participant.TYPE_MODERATOR)

				if (!isDeletable || (c.type !== 1 && Object.keys(c.participants).length > 1)) {
					actions.push({
						icon: 'icon-close',
						text: t('spreed', 'Leave conversation'),
						action: function() {
							alert('remove')
						}
					})
				}
				if (isDeletable) {
					actions.push({
						icon: 'icon-delete',
						text: t('spreed', 'Delete conversation'),
						action: function() {
							alert('delete')
						}
					})
				}

				items.push({
					id: c.token,
					icon: icon,
					router: {
						name: 'conversationRoute',
						params: {
							token: c.token
						}
					},
					utils: {
						counter: c.unreadMessages, // FIXME c.unreadMessages > 99 ? '99+' : c.unreadMessages,
						// FIXME counterAtMention: c.unreadMention ? '@' : '',
						actions: actions
					},
					text: c.displayName
				})
			})

			return items
		}
	},

	watch: {
		// watch url change and group select
		token(/* val, old */) {
			this.$store.commit('reset')
			// this.$store.dispatch('fetchActivities', this.filter);
			// this.$refs.infiniteLoading.$emit('$InfiniteLoading:reset');
		}
	},

	beforeMount() {
		this.$store.dispatch('fetchConversations')
		// this.servers = OCP.InitialState.loadState('talk', 'turn_servers')
	},

	methods: {
		goBack() {
			window.history.length > 1
				? this.$router.go(-1)
				: this.$router.push('/')
		}
	}
}
</script>
