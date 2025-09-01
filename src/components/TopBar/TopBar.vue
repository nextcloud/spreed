<!--
  - SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div
		class="top-bar"
		:class="{
			'top-bar--sidebar': isSidebar,
			'top-bar--in-call': isInCall,
			'top-bar--authorised': getUserId,
		}">
		<a
			class="top-bar__icon-wrapper"
			:class="{ 'top-bar__icon-wrapper--thread': !isInCall && currentThread }"
			role="button"
			:tabindex="0"
			:title="t('spreed', 'Back')"
			:aria-label="t('spreed', 'Back')"
			@click="handleClickAvatar">
			<IconArrowLeft
				v-show="currentThread"
				class="top-bar__icon-back bidirectional-icon"
				:size="20" />
			<ConversationIcon
				:key="conversation.token"
				:offline="isOffline"
				:item="conversation"
				:size="!isSidebar ? AVATAR.SIZE.DEFAULT : AVATAR.SIZE.COMPACT"
				:disable-menu="false"
				show-user-online-status
				:hide-favorite="false"
				:hide-call="false" />
		</a>

		<div
			v-if="!isInCall && currentThread"
			class="top-bar__wrapper">
			<IconChevronRight class="bidirectional-icon" :size="20" />

			<span class="conversation-header">
				<AvatarWrapper
					:id="currentThread.first.actorId"
					:token="token"
					:name="currentThread.first.actorDisplayName"
					:source="currentThread.first.actorType"
					:size="AVATAR.SIZE.DEFAULT"
					disable-menu
					disable-tooltip />
				<div class="conversation-header__text">
					<p class="title">
						{{ currentThread.thread.title }}
					</p>
					<p class="description">
						{{ n('spreed', '%n reply', '%n replies', currentThread.thread.numReplies) }}
					</p>
				</div>
			</span>

			<NcActions
				:aria-label="t('spreed', 'Thread notifications')"
				:title="t('spreed', 'Thread notifications')"
				:variant="threadNotificationVariant">
				<template #icon>
					<component :is="notificationLevelIcons[threadNotification]" :size="20" />
				</template>
				<NcActionButton
					v-for="level in notificationLevels"
					:key="level.value"
					:model-value="threadNotification.toString()"
					:value="level.value.toString()"
					:description="level.description"
					type="radio"
					close-after-click
					@click="chatExtrasStore.setThreadNotificationLevel(token, threadId, level.value)">
					<template #icon>
						<component :is="notificationLevelIcons[level.value]" :size="20" />
					</template>
					{{ level.label }}
				</NcActionButton>
			</NcActions>
		</div>

		<div
			v-else
			class="top-bar__wrapper"
			:data-theme-dark="isInCall ? true : undefined">
			<!-- conversation header -->
			<a
				role="button"
				class="conversation-header"
				@click="openConversationSettings">
				<div
					class="conversation-header__text"
					:class="{ 'conversation-header__text--offline': isOffline }">
					<p class="title">
						{{ conversation.displayName }}
					</p>
					<p
						v-if="showUserStatusAsDescription"
						class="description"
						:class="{ 'description__in-chat': !isInCall }">
						{{ statusMessage }}
					</p>
					<NcPopover
						v-if="conversation.description"
						no-focus-trap
						:delay="500"
						:boundary="boundaryElement"
						:popper-triggers="['hover']"
						:triggers="['hover']">
						<template #trigger="{ attrs }">
							<p
								v-bind="attrs"
								class="description"
								:class="{ 'description__in-chat': !isInCall }">
								{{ conversation.description }}
							</p>
						</template>
						<NcRichText
							class="description__popover"
							:text="conversation.description"
							use-extended-markdown />
					</NcPopover>
				</div>
			</a>

			<TasksCounter v-if="conversation.type === CONVERSATION.TYPE.NOTE_TO_SELF" />

			<!-- Upcoming meetings -->
			<CalendarEventsDialog v-if="showCalendarEvents" :token="token" />

			<!-- Call time -->
			<CallTime
				v-if="isInCall"
				:start="conversation.callStartTime" />

			<!-- Participants counter -->
			<NcButton
				v-if="isInCall && isModeratorOrUser"
				:title="participantsInCallAriaLabel"
				:aria-label="participantsInCallAriaLabel"
				variant="tertiary"
				@click="openSidebar('participants')">
				<template #icon>
					<IconAccountMultiplePlusOutline v-if="canExtendOneToOneConversation" :size="20" />
					<IconAccountMultipleOutline v-else :size="20" />
				</template>
				<template v-if="!canExtendOneToOneConversation" #default>
					{{ participantsInCall }}
				</template>
			</NcButton>
			<ExtendOneToOneDialog
				v-else-if="!isSidebar && canExtendOneToOneConversation"
				:token="token" />

			<!-- TopBar menu -->
			<TopBarMenu
				:token="token"
				:show-actions="!isSidebar"
				:is-sidebar="isSidebar"
				@open-breakout-rooms-editor="showBreakoutRoomsEditor = true" />

			<CallButton v-if="!isInCall" shrink-on-mobile />

			<!-- Breakout rooms editor -->
			<BreakoutRoomsEditor
				v-if="showBreakoutRoomsEditor"
				:token="token"
				@close="showBreakoutRoomsEditor = false" />
		</div>
	</div>
</template>

<script>
import { emit } from '@nextcloud/event-bus'
import { n, t } from '@nextcloud/l10n'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcActions from '@nextcloud/vue/components/NcActions'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcPopover from '@nextcloud/vue/components/NcPopover'
import NcRichText from '@nextcloud/vue/components/NcRichText'
import IconAccountMultipleOutline from 'vue-material-design-icons/AccountMultipleOutline.vue'
import IconAccountMultiplePlusOutline from 'vue-material-design-icons/AccountMultiplePlusOutline.vue'
import IconArrowLeft from 'vue-material-design-icons/ArrowLeft.vue'
import IconBellOffOutline from 'vue-material-design-icons/BellOffOutline.vue'
import IconBellOutline from 'vue-material-design-icons/BellOutline.vue'
import IconBellRingOutline from 'vue-material-design-icons/BellRingOutline.vue'
import IconChevronRight from 'vue-material-design-icons/ChevronRight.vue'
import AvatarWrapper from '../AvatarWrapper/AvatarWrapper.vue'
import BreakoutRoomsEditor from '../BreakoutRoomsEditor/BreakoutRoomsEditor.vue'
import CalendarEventsDialog from '../CalendarEventsDialog.vue'
import ConversationIcon from '../ConversationIcon.vue'
import ExtendOneToOneDialog from '../ExtendOneToOneDialog.vue'
import CallButton from './CallButton.vue'
import CallTime from './CallTime.vue'
import TasksCounter from './TasksCounter.vue'
import TopBarMenu from './TopBarMenu.vue'
import { useGetThreadId } from '../../composables/useGetThreadId.ts'
import { useGetToken } from '../../composables/useGetToken.ts'
import { AVATAR, CONVERSATION, PARTICIPANT } from '../../constants.ts'
import { getTalkConfig, hasTalkFeature } from '../../services/CapabilitiesManager.ts'
import { useActorStore } from '../../stores/actor.ts'
import { useChatExtrasStore } from '../../stores/chatExtras.ts'
import { useGroupwareStore } from '../../stores/groupware.ts'
import { useSidebarStore } from '../../stores/sidebar.ts'
import { getDisplayNameWithFallback } from '../../utils/getDisplayName.ts'
import { parseToSimpleMessage } from '../../utils/textParse.ts'
import { getStatusMessage } from '../../utils/userStatus.ts'

const canStartConversations = getTalkConfig('local', 'conversations', 'can-create')
const supportConversationCreationAll = hasTalkFeature('local', 'conversation-creation-all')

const notificationLevelIcons = {
	[PARTICIPANT.NOTIFY.DEFAULT]: IconBellOutline,
	[PARTICIPANT.NOTIFY.ALWAYS]: IconBellRingOutline,
	[PARTICIPANT.NOTIFY.MENTION]: IconBellOutline,
	[PARTICIPANT.NOTIFY.NEVER]: IconBellOffOutline,
}

const notificationLevels = [
	{ value: PARTICIPANT.NOTIFY.DEFAULT, label: t('spreed', 'Default'), description: t('spreed', 'Follow conversation settings') },
	{ value: PARTICIPANT.NOTIFY.ALWAYS, label: t('spreed', 'All messages') },
	{ value: PARTICIPANT.NOTIFY.MENTION, label: t('spreed', '@-mentions only') },
	{ value: PARTICIPANT.NOTIFY.NEVER, label: t('spreed', 'Off') },
]

export default {
	name: 'TopBar',

	components: {
		// Components
		AvatarWrapper,
		BreakoutRoomsEditor,
		CalendarEventsDialog,
		CallButton,
		CallTime,
		ConversationIcon,
		ExtendOneToOneDialog,
		NcActionButton,
		NcActions,
		NcButton,
		NcPopover,
		NcRichText,
		TopBarMenu,
		TasksCounter,
		// Icons
		IconAccountMultipleOutline,
		IconAccountMultiplePlusOutline,
		IconArrowLeft,
		IconChevronRight,
	},

	props: {
		isInCall: {
			type: Boolean,
			required: true,
		},

		/**
		 * In the sidebar the conversation settings are hidden
		 */
		isSidebar: {
			type: Boolean,
			default: false,
		},
	},

	setup() {
		return {
			AVATAR,
			PARTICIPANT,
			groupwareStore: useGroupwareStore(),
			sidebarStore: useSidebarStore(),
			actorStore: useActorStore(),
			chatExtrasStore: useChatExtrasStore(),
			CONVERSATION,
			threadId: useGetThreadId(),
			token: useGetToken(),
			notificationLevels,
			notificationLevelIcons,
		}
	},

	data: () => {
		return {
			showBreakoutRoomsEditor: false,
			boundaryElement: document.querySelector('.main-view'),
		}
	},

	computed: {
		currentThread() {
			if (!this.threadId) {
				return null
			}
			return this.chatExtrasStore.getThread(this.token, this.threadId)
		},

		threadNotification() {
			if (this.currentThread) {
				return this.currentThread.attendee.notificationLevel
			}
			return PARTICIPANT.NOTIFY.DEFAULT
		},

		threadNotificationVariant() {
			if ([PARTICIPANT.NOTIFY.ALWAYS, PARTICIPANT.NOTIFY.MENTION].includes(this.currentThread?.attendee.notificationLevel)) {
				return 'secondary'
			}
			return 'tertiary'
		},

		isOneToOneConversation() {
			return this.conversation.type === CONVERSATION.TYPE.ONE_TO_ONE
				|| this.conversation.type === CONVERSATION.TYPE.ONE_TO_ONE_FORMER
		},

		canExtendOneToOneConversation() {
			return canStartConversations && supportConversationCreationAll && this.isOneToOneConversation
				&& this.conversation.type !== CONVERSATION.TYPE.ONE_TO_ONE_FORMER
		},

		isModeratorOrUser() {
			return this.$store.getters.isModeratorOrUser
		},

		conversation() {
			return this.$store.getters.conversation(this.token) || this.$store.getters.dummyConversation
		},

		showUserStatusAsDescription() {
			return this.isOneToOneConversation && this.statusMessage
		},

		statusMessage() {
			return getStatusMessage(this.conversation)
		},

		/**
		 * Online status of the peer (second attendee) in one to one conversation.
		 */
		isOffline() {
			if (!this.isOneToOneConversation) {
				return false
			}

			const peer = this.$store.getters.participantsList(this.token)
				.find((participant) => participant.actorId !== this.actorStore.actorId)

			// If second attendee is not currently in the room,
			// or not invited yet to the room, show as offline
			return !peer || peer.sessionIds.length === 0
		},

		participantsInCall() {
			return this.$store.getters.participantsInCall(this.token) || ''
		},

		participantsInCallAriaLabel() {
			if (this.canExtendOneToOneConversation) {
				return t('spreed', 'Add participants to this call')
			}
			return n('spreed', '%n participant in call', '%n participants in call', this.$store.getters.participantsInCall(this.token))
		},

		showCalendarEvents() {
			return this.getUserId && !this.isInCall && !this.isSidebar
				&& this.conversation.type !== CONVERSATION.TYPE.NOTE_TO_SELF
				&& this.conversation.type !== CONVERSATION.TYPE.CHANGELOG
		},

		getUserId() {
			return this.actorStore.userId
		},
	},

	watch: {
		token: {
			immediate: true,
			handler(value) {
				if (!value || this.isSidebar || !this.getUserId) {
					// Do not fetch upcoming events for guests (401 unauthorzied) or in sidebar
					return
				}
				this.groupwareStore.getUpcomingEvents(value)
			},
		},

		currentThread: {
			immediate: true,
			handler(value) {
				if (this.threadId && value === undefined) {
					this.chatExtrasStore.fetchSingleThread(this.token, this.threadId)
				}
			},
		},
	},

	mounted() {
		document.body.classList.add('has-topbar')
	},

	beforeUnmount() {
		document.body.classList.remove('has-topbar')
	},

	methods: {
		t,
		n,
		openSidebar(activeTab) {
			this.sidebarStore.showSidebar({ activeTab })
		},

		handleClickAvatar() {
			if (this.currentThread) {
				this.$router.replace({ query: {}, hash: '' })
			} else {
				this.openConversationSettings()
			}
		},

		openConversationSettings() {
			emit('show-conversation-settings', { token: this.token })
		},
	},
}
</script>

<style lang="scss" scoped>
@import '../../assets/markdown';

.top-bar {
	--border-width: 1px;
	display: flex;
	flex-wrap: wrap;
	gap: 3px;
	align-items: center;
	justify-content: flex-end;

	z-index: 10;
	min-height: calc(var(--border-width) + 2 * (2 * var(--default-grid-baseline)) + var(--default-clickable-area));
	padding-block: var(--default-grid-baseline);
	// Reserve space for the sidebar toggle button
	padding-inline: calc(2 * var(--default-grid-baseline)) calc(2 * var(--default-grid-baseline) + var(--app-sidebar-offset, 0));
	background-color: var(--color-main-background);
	border-bottom: var(--border-width) solid var(--color-border);

	&--in-call {
		inset-inline: 0;
		border: none;
		position: absolute;
		top: 0;
		background-color: transparent;
	}

	.talk-sidebar-callview & {
		padding-inline-end: calc(var(--default-clickable-area) + var(--default-grid-baseline) * 3);
		align-items: flex-start;
		padding-block: calc(2 * var(--default-grid-baseline)) 0px;
		background: linear-gradient(to bottom, rgba(0, 0, 0, 1), rgba(0, 0, 0, 0));

		.top-bar__icon-wrapper {
			margin-inline-start: 0;
			height: var(--default-clickable-area);
			display: flex;
			align-items: center;
		}
	}

	&--sidebar {
		padding-inline-start: var(--default-grid-baseline);
	}

	&--authorised:not(.top-bar--sidebar) {
		.top-bar__icon-wrapper {
			margin-inline-start: calc(var(--default-clickable-area) + var(--default-grid-baseline));
		}
	}
}

.top-bar__wrapper {
	flex: 1 0;
	display: flex;
	gap: 3px;
	align-items: center;
	justify-content: flex-end;
}

.thread-header {
	display: flex;
	align-items: center;
	width: 100%;
	gap: var(--default-grid-baseline);
}

.top-bar__icon-wrapper {
	position: relative;
	border-radius: var(--border-radius-pill);
	transition-property: width, padding, background-color;
	transition-duration: var(--animation-quick);

	&:hover,
	&:focus,
	&:focus-visible {
		background-color: var(--color-background-darker);
	}

	&--thread {
		background-color: var(--color-background-dark);
		width: calc(var(--default-clickable-area) + 40px); // AVATAR.SIZE.DEFAULT
		padding-inline-start: var(--default-clickable-area);
	}

	.top-bar__icon-back {
		position: absolute;
		width: var(--default-clickable-area);
		height: 100%;
		top: 0;
		inset-inline-start: 0;
	}
}

.conversation-header {
	position: relative;
	display: flex;
	overflow-x: hidden;
	overflow-y: clip;
	white-space: nowrap;
	width: 0;
	flex-grow: 1;
	cursor: pointer;
	&__text {
		display: flex;
		flex-direction:column;
		flex-grow: 1;
		margin-inline-start: 8px;
		justify-content: center;
		width: 100%;
		overflow: hidden;
		// Text is guaranteed to be one line. Make line-height 20px to fit top bar
		line-height: 20px;
		&--offline {
			color: var(--color-text-maxcontrast);
		}
	}
	.title {
		font-weight: 500;
		overflow: hidden;
		text-overflow: ellipsis;
	}
	.description {
		overflow: hidden;
		text-overflow: ellipsis;
		max-width: fit-content;
		&__in-chat {
			color: var(--color-text-maxcontrast);
		}
	}
}

.description__popover {
	padding: calc(var(--default-grid-baseline) * 2);
	width: fit-content;
	max-width: 50em;

	:deep(> div) {
		@include markdown;
	}
}

.icon {
	display: flex;
}
</style>
