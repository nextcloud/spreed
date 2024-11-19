<!--
  - SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<span ref="mention" class="mention">
		<NcUserBubble v-if="size"
			:key="isDarkTheme ? 'dark' : 'light'"
			:display-name="name"
			:avatar-image="avatarUrl"
			:user="id"
			:size="size"
			:primary="primary" />
	</span>
</template>

<script>
import { loadState } from '@nextcloud/initial-state'

import NcUserBubble from '@nextcloud/vue/dist/Components/NcUserBubble.js'
import { useIsDarkTheme } from '@nextcloud/vue/dist/Composables/useIsDarkTheme.js'

import { MENTION } from '../../../../../constants.js'
import { getConversationAvatarOcsUrl, getUserProxyAvatarOcsUrl } from '../../../../../services/avatarService.ts'

export default {
	name: 'Mention',

	components: {
		NcUserBubble,
	},

	props: {
		token: {
			type: String,
			required: true,
		},
		type: {
			type: String,
			required: true,
		},
		id: {
			type: String,
			required: true,
		},
		name: {
			type: String,
			required: true,
		},
		server: {
			type: String,
			default: '',
		},
	},

	setup() {
		const isDarkTheme = useIsDarkTheme()

		return {
			isDarkTheme,
		}
	},

	data() {
		return {
			size: null,
		}
	},

	computed: {
		isMentionToAll() {
			return this.type === MENTION.TYPE.CALL
		},
		isGroupMention() {
			return [MENTION.TYPE.USERGROUP, MENTION.TYPE.GROUP].includes(this.type)
		},
		isMentionToGuest() {
			return this.type === MENTION.TYPE.GUEST || this.type === MENTION.TYPE.EMAIL
		},
		isRemoteUser() {
			return [MENTION.TYPE.USER, MENTION.TYPE.FEDERATED_USER].includes(this.type) && this.server !== ''
		},
		isCurrentGuest() {
			// On mention bubbles the id is actually "guest/ACTOR_ID" for guests
			// This is to make sure guests can never collide with users,
			// while storing them as "… @id …" in chat messages.
			// So when comparing a guest we have to prefix "guest/"
			// when comparing the id
			// However we do not prefix email accounts, so simply compare id
			return this.$store.getters.isActorGuest()
				&& (this.id === ('guest/' + this.$store.getters.getActorId())
					|| this.id === this.$store.getters.getActorId())
		},
		isCurrentUser() {
			if (this.isRemoteUser) {
				// For now, we don't highlight remote users even if they are the one
				return false
			}

			return this.$store.getters.isActorUser()
				&& this.id === this.$store.getters.getUserId()
		},
		isCurrentUserGroup() {
			return this.isGroupMention
				&& loadState('spreed', 'user_group_ids', []).includes(this.id)
		},
		primary() {
			return this.isMentionToAll || this.isCurrentUser
				|| (this.isGroupMention && this.isCurrentUserGroup)
				|| (this.isMentionToGuest && this.isCurrentGuest)
		},
		avatarUrl() {
			if (this.isRemoteUser) {
				return this.token
					? getUserProxyAvatarOcsUrl(this.token, this.id + '@' + this.server, this.isDarkTheme, 64)
					: 'icon-user-forced-white'
			} else if (this.isGroupMention) {
				return 'icon-group-forced-white'
			} else if (this.isMentionToGuest) {
				return 'icon-user-forced-white'
			} else if (!this.isMentionToAll) {
				return undefined
			}

			return getConversationAvatarOcsUrl(this.id, this.isDarkTheme)
		},
	},

	mounted() {
		this.size = parseInt(window.getComputedStyle(this.$refs.mention).fontSize, 10) * 4 / 3 ?? 20
	}
}
</script>

<style lang="scss" scoped>
.mention {
	display: contents;
	white-space: nowrap;
}
</style>
