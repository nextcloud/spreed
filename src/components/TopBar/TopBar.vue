<!--
  - SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="top-bar"
		:class="{
			'top-bar--sidebar': isSidebar,
			'top-bar--in-call': isInCall,
			'top-bar--authorised': getUserId,
		}">
		<ConversationIcon :key="conversation.token"
			class="conversation-icon"
			:offline="isPeerInactive"
			:item="conversation"
			:size="AVATAR.SIZE.DEFAULT"
			:disable-menu="false"
			show-user-online-status
			:hide-favorite="false"
			:hide-call="false" />

		<div class="top-bar__wrapper" :data-theme-dark="isInCall">
			<!-- conversation header -->
			<a role="button"
				class="conversation-header"
				@click="openConversationSettings">
				<div class="conversation-header__text"
					:class="{'conversation-header__text--offline': isPeerInactive}">
					<p class="title">
						{{ conversation.displayName }}
					</p>
					<p v-if="showUserStatusAsDescription"
						class="description"
						:class="{'description__in-chat' : !isInCall }">
						{{ statusMessage }}
					</p>
					<NcPopover v-if="conversation.description"
						:focus-trap="false"
						:delay="500"
						:boundary="boundaryElement"
						:popper-triggers="['hover']"
						:triggers="['hover']">
						<template #trigger="{ attrs }">
							<p v-bind="attrs"
								class="description"
								:class="{'description__in-chat' : !isInCall }">
								{{ conversation.description }}
							</p>
						</template>
						<!-- eslint-disable-next-line vue/no-v-html -->
						<div class="description__popover" v-html="renderedDescription" />
					</NcPopover>
				</div>
			</a>

			<TasksCounter v-if="conversation.type === CONVERSATION.TYPE.NOTE_TO_SELF" />

			<!-- Upcoming event -->
			<a v-if="showUpcomingEvent"
				class="upcoming-event"
				:href="nextEvent.calendarAppUrl"
				:title="t('spreed', 'Open Calendar')"
				target="_blank">
				<div class="icon">
					<CalendarBlank :size="20" />
				</div>
				<div class="event-info">
					<p class="event-info__header">
						{{ t('spreed', 'Next call') }}
					</p>
					<p> {{ eventInfo }} </p>
				</div>
			</a>

			<!-- Call time -->
			<CallTime v-if="isInCall"
				:start="conversation.callStartTime" />

			<!-- Participants counter -->
			<NcButton v-if="isInCall && !isOneToOneConversation && isModeratorOrUser"
				:title="participantsInCallAriaLabel"
				:aria-label="participantsInCallAriaLabel"
				type="tertiary"
				@click="openSidebar('participants')">
				<template #icon>
					<AccountMultiple :size="20" />
				</template>
				{{ participantsInCall }}
			</NcButton>

			<!-- Reactions menu -->
			<ReactionMenu v-if="isInCall && hasReactionSupport"
				:token="token"
				:supported-reactions="supportedReactions"
				:local-call-participant-model="localCallParticipantModel" />

			<!-- Local media controls -->
			<TopBarMediaControls v-if="isInCall"
				:token="token"
				:model="localMediaModel"
				:is-sidebar="isSidebar"
				:local-call-participant-model="localCallParticipantModel" />

			<!-- TopBar menu -->
			<TopBarMenu :token="token"
				:show-actions="!isSidebar"
				:is-sidebar="isSidebar"
				:model="localMediaModel"
				@open-breakout-rooms-editor="showBreakoutRoomsEditor = true" />

			<CallButton shrink-on-mobile :hide-text="isSidebar" :is-screensharing="!!localMediaModel.attributes.localScreen" />

			<!-- Breakout rooms editor -->
			<BreakoutRoomsEditor v-if="showBreakoutRoomsEditor"
				:token="token"
				@close="showBreakoutRoomsEditor = false" />
		</div>
	</div>
</template>

<script>
import AccountMultiple from 'vue-material-design-icons/AccountMultiple.vue'
import CalendarBlank from 'vue-material-design-icons/CalendarBlank.vue'

import { emit } from '@nextcloud/event-bus'
import { t, n } from '@nextcloud/l10n'
import moment from '@nextcloud/moment'

import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcPopover from '@nextcloud/vue/dist/Components/NcPopover.js'
import { useIsMobile } from '@nextcloud/vue/dist/Composables/useIsMobile.js'
import richEditor from '@nextcloud/vue/dist/Mixins/richEditor.js'

import CallButton from './CallButton.vue'
import CallTime from './CallTime.vue'
import ReactionMenu from './ReactionMenu.vue'
import TasksCounter from './TasksCounter.vue'
import TopBarMediaControls from './TopBarMediaControls.vue'
import TopBarMenu from './TopBarMenu.vue'
import BreakoutRoomsEditor from '../BreakoutRoomsEditor/BreakoutRoomsEditor.vue'
import ConversationIcon from '../ConversationIcon.vue'

import { useGetParticipants } from '../../composables/useGetParticipants.js'
import { AVATAR, CONVERSATION } from '../../constants.js'
import { getTalkConfig } from '../../services/CapabilitiesManager.ts'
import { useChatExtrasStore } from '../../stores/chatExtras.js'
import { useSidebarStore } from '../../stores/sidebar.js'
import { getStatusMessage } from '../../utils/userStatus.ts'
import { localCallParticipantModel, localMediaModel } from '../../utils/webrtc/index.js'

export default {
	name: 'TopBar',

	components: {
		// Components
		BreakoutRoomsEditor,
		CallButton,
		CallTime,
		ConversationIcon,
		TopBarMediaControls,
		NcButton,
		NcPopover,
		TopBarMenu,
		TasksCounter,
		ReactionMenu,
		// Icons
		AccountMultiple,
		CalendarBlank,
	},

	mixins: [richEditor],

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
		useGetParticipants()

		return {
			AVATAR,
			localCallParticipantModel,
			localMediaModel,
			chatExtrasStore: useChatExtrasStore(),
			sidebarStore: useSidebarStore(),
			isMobile: useIsMobile(),
			CONVERSATION,
		}
	},

	data: () => {
		return {
			showBreakoutRoomsEditor: false,
			boundaryElement: document.querySelector('.main-view'),
		}
	},

	computed: {
		isOneToOneConversation() {
			return this.conversation.type === CONVERSATION.TYPE.ONE_TO_ONE
				|| this.conversation.type === CONVERSATION.TYPE.ONE_TO_ONE_FORMER
		},

		isModeratorOrUser() {
			return this.$store.getters.isModeratorOrUser
		},

		token() {
			return this.$store.getters.getToken()
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

		renderedDescription() {
			return this.renderContent(this.conversation.description)
		},

		/**
		 * Current actor id
		 */
		actorId() {
			return this.$store.getters.getActorId()
		},

		/**
		 * Online status of the peer in one to one conversation.
		 */
		isPeerInactive() {
			// Only compute this in one-to-one conversations
			if (!this.isOneToOneConversation) {
				return undefined
			}

			// Get the 1 to 1 peer
			let peer
			const participants = this.$store.getters.participantsList(this.token)
			for (const participant of participants) {
				if (participant.actorId !== this.actorId) {
					peer = participant
				}
			}

			if (peer) {
				return !peer.sessionIds.length
			} else return false
		},

		participantsInCall() {
			return this.$store.getters.participantsInCall(this.token) || ''
		},

		participantsInCallAriaLabel() {
			return n('spreed', '%n participant in call', '%n participants in call', this.$store.getters.participantsInCall(this.token))
		},

		supportedReactions() {
			return getTalkConfig(this.token, 'call', 'supported-reactions')
		},

		hasReactionSupport() {
			return this.isInCall && this.supportedReactions?.length > 0
		},

		nextEvent() {
			return this.chatExtrasStore.getNextEvent(this.token)
		},

		eventInfo() {
			if (!this.nextEvent) {
				return null
			}

			// If timestamp is in the past, show "now"
			if (this.nextEvent.start <= moment().unix()) {
				return t('spreed', 'Now')
			}

			return moment(this.nextEvent.start * 1000).calendar()
		},

		showUpcomingEvent() {
			return this.nextEvent && !this.isInCall && !this.isSidebar && !this.isMobile
				&& this.conversation.type !== CONVERSATION.TYPE.NOTE_TO_SELF
		},

		getUserId() {
			return this.$store.getters.getUserId()
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
				this.chatExtrasStore.getUpcomingEvents(value)
			}
		},
	},

	mounted() {
		document.body.classList.add('has-topbar')
	},

	beforeDestroy() {
		document.body.classList.remove('has-topbar')
	},

	methods: {
		t,
		n,
		openSidebar(activeTab) {
			this.sidebarStore.showSidebar({ activeTab })
		},

		openConversationSettings() {
			emit('show-conversation-settings', { token: this.token })
		},
	},
}
</script>

<style lang="scss" scoped>
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
		right: 0;
		border: none;
		position: absolute;
		top: 0;
		left: 0;
		background-color: transparent;
	}

	.talk-sidebar-callview & {
		margin-right: var(--default-clickable-area);
	}

	&--sidebar {
		padding: calc(2 * var(--default-grid-baseline));

		.conversation-icon {
			margin-left: 0;
		}
	}

	&--authorised {
		.conversation-icon {
			margin-left: calc(var(--default-clickable-area) + var(--default-grid-baseline));
		}
	}
}

.top-bar__wrapper {
	flex: 1 0;
	display: flex;
	flex-wrap: wrap;
	gap: 3px;
	align-items: center;
	justify-content: flex-end;
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
		margin-left: 8px;
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
	padding: var(--default-grid-baseline);
	width: fit-content;
	max-width: 50em;
}

.icon {
	display: flex;
}

.upcoming-event {
	display: flex;
	flex-direction: row;
	gap: calc(var(--default-grid-baseline) * 2);
	padding: 0 calc(var(--default-grid-baseline) * 2);
	background-color: rgba(var(--color-info-rgb), 0.1);
	height: 100%;
	border-radius: var(--border-radius);
}

.event-info {
	display: flex;
	flex-direction: column;
	justify-content: center;
	line-height: 20px;

	&__header {
		font-weight: 500;
	}
}

</style>
