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
import { useIsDarkTheme } from '@nextcloud/vue/composables/useIsDarkTheme'
import NcUserBubble from '@nextcloud/vue/components/NcUserBubble'
import { MENTION } from '../../../../../constants.ts'
import { getConversationAvatarOcsUrl, getUserProxyAvatarOcsUrl } from '../../../../../services/avatarService.ts'
import { useActorStore } from '../../../../../stores/actor.ts'

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
			actorStore: useActorStore(),
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

		isTeamMention() {
			return [MENTION.TYPE.CIRCLE, MENTION.TYPE.TEAM].includes(this.type)
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
			return this.actorStore.isActorGuest
				&& (this.id === ('guest/' + this.actorStore.actorId)
					|| this.id === this.actorStore.actorId)
		},

		isCurrentUser() {
			if (this.isRemoteUser) {
				// For now, we don't highlight remote users even if they are the one
				return false
			}

			return this.actorStore.isActorUser
				&& this.id === this.actorStore.userId
		},

		isCurrentUserGroup() {
			return this.isGroupMention && this.actorStore.isActorMemberOfGroup(this.id)
		},

		isCurrentUserTeam() {
			return this.isTeamMention && this.actorStore.isActorMemberOfTeam(this.id)
		},

		primary() {
			return this.isMentionToAll
				|| this.isCurrentUser
				|| this.isCurrentUserGroup
				|| this.isCurrentUserTeam
				|| (this.isMentionToGuest && this.isCurrentGuest)
		},

		avatarUrl() {
			if (this.isRemoteUser) {
				return this.token
					? getUserProxyAvatarOcsUrl(this.token, this.id + '@' + this.server, this.isDarkTheme, 64)
					: 'icon-user-forced-white'
			} else if (this.isGroupMention) {
				return 'icon-group-forced-white'
			} else if (this.isTeamMention) {
				return 'icon-team-forced-white'
			} else if (this.isMentionToGuest) {
				return 'icon-user-forced-white'
			} else if (!this.isMentionToAll) {
				return undefined
			}

			return getConversationAvatarOcsUrl(this.id, this.isDarkTheme)
		},
	},

	mounted() {
		this.size = parseInt(window.getComputedStyle(this.$refs.mention).fontSize ?? 15, 10) * 4 / 3
	},
}
</script>

<style lang="scss" scoped>
.mention {
	display: contents;
	white-space: nowrap;
}
</style>
