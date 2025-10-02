<!--
  - SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="lobby">
		<div class="lobby__header">
			<IconRoomServiceOutline :size="64" />
			<h2>{{ currentConversationName }}</h2>

			<p class="lobby__timer">
				{{ t('spreed', 'You are currently waiting in the lobby') }}
			</p>

			<p
				v-if="lobbyTimer"
				class="lobby__countdown">
				{{ message }}
				<span
					v-if="relativeDate"
					class="lobby__countdown relative-timestamp"
					:title="startTime">
					- {{ relativeDate }}
				</span>
			</p>

			<div class="lobby__description">
				<NcRichText
					:text="conversation.description"
					dir="auto"
					autolink
					use-extended-markdown />
			</div>
		</div>
		<MediaSettings :is-dialog="false" />
	</div>
</template>

<script>
import { t } from '@nextcloud/l10n'
import NcRichText from '@nextcloud/vue/components/NcRichText'
import IconRoomServiceOutline from 'vue-material-design-icons/RoomServiceOutline.vue'
import MediaSettings from '../components/MediaSettings/MediaSettings.vue'
import { useGetToken } from '../composables/useGetToken.ts'
import { formatDateTime, futureRelativeTime, ONE_DAY_IN_MS } from '../utils/formattedTime.ts'

export default {

	name: 'LobbyScreen',

	components: {
		NcRichText,
		IconRoomServiceOutline,
		MediaSettings,
	},

	setup() {
		return {
			token: useGetToken(),
		}
	},

	computed: {
		conversation() {
			return this.$store.getters.conversation(this.token)
		},

		currentConversationName() {
			return this.conversation ? this.conversation.displayName : ''
		},

		lobbyTimer() {
			return this.conversation.lobbyTimer * 1000
		},

		relativeDate() {
			if (Math.abs(Date.now() - this.lobbyTimer) > ONE_DAY_IN_MS) {
				// No relative time
				return ''
			}
			if (Math.abs(Date.now() - this.lobbyTimer) < 45000) {
				return t('spreed', 'The meeting will start soon')
			}
			return futureRelativeTime(this.lobbyTimer)
		},

		startTime() {
			return formatDateTime(this.lobbyTimer, 'longDateWithTime')
		},

		message() {
			return t('spreed', 'This meeting is scheduled for {startTime}', { startTime: this.startTime })
		},
	},

	methods: {
		t,
	},
}
</script>

<style lang="scss" scoped>
@import '../assets/variables';
@import '../assets/markdown';

.lobby {
	display: flex;
	flex-direction: column;
	margin: auto;

	&__header {
		display: flex;
		flex-direction: column;
		align-items: center;
		text-align: center;
	}

	&__countdown,
	&__description {
		margin-top: 25px;
		max-width: $messages-list-max-width;
	}

	:deep(.rich-text--wrapper) {
		text-align: start;
		@include markdown;
	}
}
</style>
