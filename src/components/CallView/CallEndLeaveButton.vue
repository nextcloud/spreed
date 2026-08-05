<!--
  - SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<script>
import { emit } from '@nextcloud/event-bus'
import { t } from '@nextcloud/l10n'
import { useIsMobile } from '@nextcloud/vue/composables/useIsMobile'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcActions from '@nextcloud/vue/components/NcActions'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import IconArrowLeft from 'vue-material-design-icons/ArrowLeft.vue'
import IconChevronUp from 'vue-material-design-icons/ChevronUp.vue'
import IconPhoneHangupOutline from 'vue-material-design-icons/PhoneHangupOutline.vue'
import IconPhoneOffOutline from 'vue-material-design-icons/PhoneOffOutline.vue'
import { useGetToken } from '../../composables/useGetToken.ts'
import { CONVERSATION, PARTICIPANT } from '../../constants.ts'
import { EventBus } from '../../services/EventBus.ts'
import { useActorStore } from '../../stores/actor.ts'
import { useBreakoutRoomsStore } from '../../stores/breakoutRooms.ts'
import { useCallViewStore } from '../../stores/callView.ts'
import { isConversationPhoneRoom } from '../../utils/conversation.ts'

export default {
	name: 'CallEndLeaveButton',

	components: {
		NcActions,
		NcActionButton,
		NcButton,
		// Icons
		IconArrowLeft,
		IconChevronUp,
		IconPhoneHangupOutline,
		IconPhoneOffOutline,
		NcLoadingIcon,
	},

	props: {
		/**
		 * Whether to render button as tertiary (to reduce drawn attention)
		 */
		isScreensharing: {
			type: Boolean,
			default: false,
		},

		/**
		 * Whether to use text on button (e.g. at sidebar)
		 */
		hideText: {
			type: Boolean,
			default: false,
		},

		/**
		 * Whether to use text on button at mobile view
		 */
		shrinkOnMobile: {
			type: Boolean,
			default: false,
		},
	},

	setup() {
		return {
			actorStore: useActorStore(),
			token: useGetToken(),
			breakoutRoomsStore: useBreakoutRoomsStore(),
			callViewStore: useCallViewStore(),
			isMobile: useIsMobile(),
		}
	},

	data() {
		return {
			loading: false,
		}
	},

	computed: {
		conversation() {
			return this.$store.getters.conversation(this.token) || this.$store.getters.dummyConversation
		},

		showButtonText() {
			return !this.hideText && (!this.isMobile || !this.shrinkOnMobile)
		},

		participantType() {
			return this.conversation.participantType
		},

		canEndForAll() {
			return (this.participantType === PARTICIPANT.TYPE.OWNER
				|| this.participantType === PARTICIPANT.TYPE.MODERATOR
				|| this.participantType === PARTICIPANT.TYPE.GUEST_MODERATOR)
			&& !this.isBreakoutRoom
		},

		leaveCallLabel() {
			return t('spreed', 'Leave call')
		},

		backToMainRoomLabel() {
			return t('spreed', 'Back to main room')
		},

		leaveCallActionsLabel() {
			return t('spreed', 'More actions')
		},

		endCallLabel() {
			return t('spreed', 'End call')
		},

		isBreakoutRoom() {
			return this.conversation.objectType === CONVERSATION.OBJECT_TYPE.BREAKOUT_ROOM
		},

		isPhoneRoom() {
			return isConversationPhoneRoom(this.conversation)
		},

		leaveCallButtonVariant() {
			if (this.isScreensharing) {
				return 'tertiary'
			}
			return this.isBreakoutRoom ? 'primary' : 'error'
		},

		isVoiceRoom() {
			return Boolean(this.conversation.attributes & CONVERSATION.ATTRIBUTE.VOICE_ROOM)
		},
	},

	methods: {
		t,

		async leaveCall(endMeetingForAll = false) {
			if (endMeetingForAll) {
				console.info('End meeting for everyone')
			} else {
				console.info('Leaving call')
			}

			if (this.isVoiceRoom) {
				this.$router.push({ name: 'root' })
				// Call ending is handled in App.vue
				return
			}

			// Remove selected participant
			this.callViewStore.setSelectedVideoPeerId(null)
			this.loading = true

			// Open navigation
			if (!this.isMobile) {
				emit('toggle-navigation', {
					open: true,
				})
			}
			await this.$store.dispatch('leaveCall', {
				token: this.token,
				participantIdentifier: this.actorStore.participantIdentifier,
				all: endMeetingForAll,
			})
			this.loading = false
		},

		async switchToParentRoom() {
			EventBus.emit('switch-to-conversation', {
				token: this.breakoutRoomsStore.getParentRoomToken(this.token),
			})
		},
	},
}
</script>

<template>
	<NcButton
		v-if="canEndForAll && isPhoneRoom"
		:aria-label="endCallLabel"
		class="leave-call"
		variant="error"
		:disabled="loading"
		@click="leaveCall(true)">
		<template #icon>
			<NcLoadingIcon v-if="loading" :size="20" />
			<IconPhoneHangupOutline v-else :size="20" />
		</template>
		<template v-if="showButtonText" #default>
			{{ endCallLabel }}
		</template>
	</NcButton>
	<NcButton
		v-else-if="(!canEndForAll || isVoiceRoom) && !isBreakoutRoom"
		:aria-label="leaveCallLabel"
		class="leave-call"
		:variant="isScreensharing ? 'tertiary' : 'error'"
		:disabled="loading"
		@click="leaveCall(false)">
		<template #icon>
			<NcLoadingIcon v-if="loading" :size="20" />
			<IconPhoneHangupOutline v-else :size="20" />
		</template>
		<template v-if="showButtonText" #default>
			{{ leaveCallLabel }}
		</template>
	</NcButton>
	<NcActions
		v-else-if="(canEndForAll || isBreakoutRoom)"
		class="leave-call leave-call-actions--split"
		:disabled="loading"
		:forceName="showButtonText"
		placement="top-end"
		:aria-label="leaveCallActionsLabel"
		:inline="1"
		:variant="leaveCallButtonVariant">
		<template #icon>
			<IconChevronUp :size="20" />
		</template>
		<NcActionButton
			v-if="isBreakoutRoom"
			:aria-label="backToMainRoomLabel"
			@click="switchToParentRoom">
			<template #icon>
				<IconArrowLeft class="bidirectional-icon" :size="20" />
			</template>
			<template v-if="showButtonText" #default>
				{{ backToMainRoomLabel }}
			</template>
		</NcActionButton>
		<NcActionButton
			class="leave-call-button--split"
			:aria-label="leaveCallLabel"
			@click="leaveCall(false)">
			<template #icon>
				<NcLoadingIcon v-if="loading" :size="20" />
				<IconPhoneHangupOutline v-else :size="20" />
			</template>
			<template v-if="showButtonText || isBreakoutRoom" #default>
				{{ leaveCallLabel }}
			</template>
		</NcActionButton>
		<NcActionButton v-if="canEndForAll && !isVoiceRoom" @click="leaveCall(true)">
			<template #icon>
				<IconPhoneOffOutline :size="20" />
			</template>
			{{ t('spreed', 'End call for everyone') }}
		</NcActionButton>
	</NcActions>
</template>

<style lang="scss" scoped>
.leave-call.button-vue--error,
.leave-call :deep(.button-vue--error) {
	// Overwrite default button colors for leaving call
	background-color: #FF3333 !important; // Nextcloud 31 --color-error
	color: var(--color-primary-text) !important;

	&:hover:not(:disabled) {
		background-color: var(--color-error-hover) !important;
	}
}

.leave-call-actions--split {
	gap: 1px !important;
}

.leave-call-actions--split :deep(.action-item--single) {
	border-start-end-radius: 2px;
	border-end-end-radius: 2px;
}

.leave-call-actions--split :deep(.action-item__menutoggle) {
	--button-size: var(--clickable-area-small);
	height: var(--default-clickable-area);
	border-start-start-radius: 2px;
	border-end-start-radius: 2px;
}
</style>
