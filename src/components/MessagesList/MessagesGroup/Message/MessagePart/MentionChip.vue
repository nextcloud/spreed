<!--
  - SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script>
import { generateAvatarUrl } from '@nextcloud/router'
import { useIsDarkTheme } from '@nextcloud/vue/composables/useIsDarkTheme'
import { ref } from 'vue'
import NcChip from '@nextcloud/vue/components/NcChip'
import { MENTION } from '../../../../../constants.ts'
import { getConversationAvatarOcsUrl, getUserProxyAvatarOcsUrl } from '../../../../../services/avatarService.ts'
import { useActorStore } from '../../../../../stores/actor.ts'

export default {
	name: 'MentionChip',

	components: {
		NcChip,
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
		const failed = ref(false)
		const isDarkTheme = useIsDarkTheme()

		return {
			failed,
			isDarkTheme,
			actorStore: useActorStore(),
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

		isMentionToUser() {
			return [MENTION.TYPE.USER, MENTION.TYPE.FEDERATED_USER].includes(this.type)
		},

		isRemoteUser() {
			return this.isMentionToUser && this.server !== ''
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

		variant() {
			if (this.isMentionToAll
				|| this.isCurrentUser
				|| this.isCurrentUserGroup
				|| this.isCurrentUserTeam
				|| (this.isMentionToGuest && this.isCurrentGuest)) {
				return 'primary'
			}
			return 'tertiary'
		},

		imageSource() {
			if (this.isMentionToUser) {
				const url = this.isRemoteUser
					? (this.token ? getUserProxyAvatarOcsUrl(this.token, this.id + '@' + this.server, this.isDarkTheme, 64) : null)
					: generateAvatarUrl(this.id, { isDarkTheme: this.isDarkTheme, size: 64 })
				return { url, icon: 'icon-user-forced-white' }
			} else if (this.isGroupMention) {
				return { url: null, icon: 'icon-group-forced-white' }
			} else if (this.isTeamMention) {
				return { url: null, icon: 'icon-team-forced-white' }
			} else if (this.isMentionToGuest) {
				return { url: null, icon: 'icon-user-forced-white' }
			} else if (!this.isMentionToAll) {
				return { url: null, icon: 'icon-user-forced-white' }
			}

			return { url: getConversationAvatarOcsUrl(this.id, this.isDarkTheme), icon: 'icon-group-forced-white' }
		},
	},
}
</script>

<template>
	<NcChip
		:text="name"
		:variant
		class="mention-chip"
		:class="{ 'mention-chip--dark': isDarkTheme }"
		noClose>
		<template #icon>
			<img
				v-if="!failed && imageSource.url"
				:key="imageSource.url"
				:src="imageSource.url"
				:alt="name"
				class="mention-chip__icon"
				@error="failed = true">
			<span
				v-else
				class="mention-chip__icon"
				:class="[imageSource.icon]" />
		</template>
	</NcChip>
</template>

<style lang="scss" scoped>
.mention-chip {
	// Overwrite NcChip styles to have inline size from messages text
	--chip-size: calc(4em / 3) !important;
	display: inline-flex !important;
	vertical-align: bottom !important;
	margin-block-end: 2px !important;

	&__icon {
		display: block;
		width: calc(var(--chip-size) - 4px);
		height: calc(var(--chip-size) - 4px);
		max-width: 100%;
		max-height: 100%;
		border-radius: 50%;
		background-color: var(--color-text-maxcontrast-default);
		background-size: calc(var(--chip-size) / 2);
	}

	&--dark &__icon {
		background-color: var(--color-background-darker);
	}
}
</style>
