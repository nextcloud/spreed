<!--
  - SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<NcListItem :name="computedName"
		:data-nav-id="participantNavigationId"
		class="participant"
		:class="{ 'participant--offline': isOffline }"
		:aria-label="participantAriaLabel"
		:actions-aria-label="participantSettingsAriaLabel"
		force-display-actions
		force-menu>
		<!-- Participant's avatar -->
		<template #icon>
			<AvatarWrapper :id="participant.actorId"
				:token="token"
				:name="displayName"
				:source="participant.actorType"
				disable-tooltip
				:show-user-status="showUserStatus"
				:preloaded-user-status="preloadedUserStatus"
				:highlighted="isSpeakingStatusAvailable && isParticipantSpeaking"
				:offline="isOffline" />
		</template>

		<template #name>
			<!-- First line: participant's name and type -->
			<span class="participant__user" :title="userNameTitle">
				<span class="participant__user-name">{{ computedName }}</span>
				<span v-if="showModeratorLabel" class="participant__user-badge">({{ t('spreed', 'moderator') }})</span>
				<span v-if="isBridgeBotUser" class="participant__user-badge">({{ t('spreed', 'bot') }})</span>
				<span v-if="isGuestActor || isEmailActor" class="participant__user-badge">({{ t('spreed', 'guest') }})</span>
				<span v-if="!isSelf && isLobbyEnabled && !canSkipLobby" class="participant__user-badge">({{ t('spreed', 'in the lobby') }})</span>
			</span>
		</template>

		<template v-if="statusMessage" #subname>
			<!-- Second line: participant status message if applicable -->
			<span class="participant__status"
				:class="{ 'participant__status--highlighted': isParticipantSpeaking }"
				:title="statusMessage">
				{{ statusMessage }}
			</span>
		</template>

		<template #extra-actions>
			<!-- Phone participant dial action -->
			<template v-if="isInCall && canBeModerated && isPhoneActor">
				<NcButton v-if="!participant.inCall"
					variant="success"
					:aria-label="t('spreed', 'Dial out phone')"
					:title="t('spreed', 'Dial out phone')"
					:disabled="disabled"
					@click="dialOutPhoneNumber">
					<template #icon>
						<IconPhoneDialOutline :size="20" />
					</template>
				</NcButton>
				<template v-else>
					<NcButton variant="error"
						:aria-label="t('spreed', 'Hang up phone')"
						:title="t('spreed', 'Hang up phone')"
						:disabled="disabled"
						@click="hangupPhoneNumber">
						<template #icon>
							<IconPhoneHangupOutline :size="20" />
						</template>
					</NcButton>
					<DialpadPanel :disabled="disabled"
						container="#tab-participants"
						dialing
						@dial-type="dialType" />
				</template>
			</template>

			<!-- Call state icon -->
			<component :is="callIcon.icon"
				v-else-if="callIcon"
				class="participant__call-state"
				:title="callIcon.title"
				:size="callIcon.size" />

			<!-- Grant or revoke lobby permissions (inline button) -->
			<template v-if="showToggleLobbyAction">
				<NcButton v-if="canSkipLobby"
					variant="tertiary"
					:title="t('spreed', 'Move back to lobby')"
					:aria-label="t('spreed', 'Move back to lobby')"
					@click="setLobbyPermission(false)">
					<template #icon>
						<IconAccountMinusOutline :size="20" />
					</template>
				</NcButton>
				<NcButton v-else
					variant="tertiary"
					:title="t('spreed', 'Move to conversation')"
					:aria-label="t('spreed', 'Move to conversation')"
					@click="setLobbyPermission(true)">
					<template #icon>
						<IconAccountPlusOutline :size="20" />
					</template>
				</NcButton>
			</template>
		</template>

		<!-- Participant's actions menu -->
		<template v-if="showParticipantActions && actionIcon" #actions-icon>
			<component :is="actionIcon" :size="20" />
		</template>
		<template v-if="showParticipantActions" #actions>
			<!-- Information and rights -->
			<NcActionText v-if="attendeePin" :name="t('spreed', 'Dial-in PIN')">
				<template #icon>
					<IconLockOutline :size="20" />
				</template>
				{{ attendeePin }}
			</NcActionText>
			<!-- Grant or revoke moderator permissions -->
			<NcActionButton v-if="canBeDemoted"
				key="demote-moderator"
				close-after-click
				@click="demoteFromModerator">
				<template #icon>
					<IconAccountOutline :size="20" />
				</template>
				{{ t('spreed', 'Demote from moderator') }}
			</NcActionButton>
			<NcActionButton v-else-if="canBePromoted"
				key="promote-moderator"
				close-after-click
				@click="promoteToModerator">
				<template #icon>
					<IconCrownOutline :size="20" />
				</template>
				{{ t('spreed', 'Promote to moderator') }}
			</NcActionButton>
			<NcActionButton v-if="canBeModerated && isEmailActor"
				key="resend-invitation"
				close-after-click
				@click="resendInvitation">
				<template #icon>
					<IconEmailOutline :size="20" />
				</template>
				{{ t('spreed', 'Resend invitation') }}
			</NcActionButton>
			<NcActionButton v-if="canSendCallNotification"
				key="send-call-notification"
				close-after-click
				@click="sendCallNotification">
				<template #icon>
					<IconBellOutline :size="20" />
				</template>
				{{ t('spreed', 'Send call notification') }}
			</NcActionButton>
			<template v-if="canBeModerated && isPhoneActor">
				<NcActionButton v-if="!conversation.hasCall && !isInCall && !participant.callId"
					key="dial-out-phone-number"
					close-after-click
					@click="dialOutPhoneNumber">
					<template #icon>
						<IconPhoneDialOutline :size="20" />
					</template>
					{{ t('spreed', 'Dial out phone number') }}
				</NcActionButton>
				<template v-else-if="isInCall && participant.callId">
					<NcActionButton v-if="phoneMuteState === 'hold'"
						key="resume-call-phone-number"
						close-after-click
						@click="unmutePhoneNumber">
						<template #icon>
							<IconPhoneInTalkOutline :size="20" />
						</template>
						{{ t('spreed', 'Resume call for phone number') }}
					</NcActionButton>
					<template v-else>
						<NcActionButton key="hold-call-phone-number"
							close-after-click
							@click="holdPhoneNumber">
							<template #icon>
								<IconPhonePausedOutline :size="20" />
							</template>
							{{ t('spreed', 'Put phone number on hold') }}
						</NcActionButton>
						<NcActionButton v-if="phoneMuteState === 'muted'"
							key="unmute-call-phone-number"
							close-after-click
							@click="unmutePhoneNumber">
							<template #icon>
								<IconMicrophoneOutline :size="20" />
							</template>
							{{ t('spreed', 'Unmute phone number') }}
						</NcActionButton>
						<NcActionButton v-else
							key="mute-call-phone-number"
							close-after-click
							@click="mutePhoneNumber">
							<template #icon>
								<NcIconSvgWrapper :svg="IconMicrophoneOffOutline" :size="20" />
							</template>
							{{ t('spreed', 'Mute phone number') }}
						</NcActionButton>
					</template>
				</template>
				<NcActionButton key="copy-phone-number"
					close-after-click
					@click="copyPhoneNumber">
					<template #icon>
						<IconContentCopy :size="20" />
					</template>
					{{ t('spreed', 'Copy phone number') }}
				</NcActionButton>
			</template>

			<NcActionSeparator v-if="canBeModerated && isPhoneActor && showPermissionsOptions" />

			<!-- Permissions -->
			<template v-if="showPermissionsOptions">
				<NcActionButton v-if="hasNonDefaultPermissions"
					key="reset-permissions"
					close-after-click
					@click="applyDefaultPermissions">
					<template #icon>
						<IconLockReset :size="20" />
					</template>
					{{ t('spreed', 'Reset custom permissions') }}
				</NcActionButton>
				<NcActionButton key="grant-all-permissions"
					close-after-click
					@click="grantAllPermissions">
					<template #icon>
						<IconLockOpenVariantOutline :size="20" />
					</template>
					{{ t('spreed', 'Grant all permissions') }}
				</NcActionButton>
				<NcActionButton key="remove-all-permissions"
					close-after-click
					@click="removeAllPermissions">
					<template #icon>
						<IconLockOutline :size="20" />
					</template>
					{{ t('spreed', 'Remove all permissions') }}
				</NcActionButton>
				<NcActionButton key="edit-permissions"
					close-after-click
					@click="permissionsEditor = true">
					<template #icon>
						<IconPencilOutline :size="20" />
					</template>
					{{ t('spreed', 'Edit permissions') }}
				</NcActionButton>
			</template>

			<NcActionSeparator v-if="showPermissionsOptions && canBeModerated" />

			<!-- Remove -->
			<NcActionButton v-if="canBeModerated"
				key="remove-participant"
				class="critical"
				close-after-click
				@click="isRemoveDialogOpen = true">
				<template #icon>
					<IconDeleteOutline :size="20" />
				</template>
				{{ removeParticipantLabel }}
			</NcActionButton>
		</template>

		<template #extra>
			<ParticipantPermissionsEditor v-if="showPermissionsOptions && permissionsEditor"
				:actor-id="participant.actorId"
				close-after-click
				:participant="participant"
				:token="token"
				@close="permissionsEditor = false" />

			<!-- Confirmation required to remove participant -->
			<NcDialog v-if="canBeModerated && isRemoveDialogOpen"
				v-model:open="isRemoveDialogOpen"
				:name="removeParticipantLabel">
				<p> {{ removeDialogMessage }} </p>
				<template v-if="showBanOption">
					<NcCheckboxRadioSwitch v-model="isBanParticipant">
						{{ t('spreed', 'Also ban from this conversation') }}
					</NcCheckboxRadioSwitch>
					<template v-if="isBanParticipant">
						<NcTextArea v-if="isBanParticipant"
							v-model="internalNote"
							class="participant-dialog__input"
							resize="vertical"
							:label="t('spreed', 'Internal note (reason to ban)')"
							:error="!!maxLengthWarning"
							:helper-text="maxLengthWarning" />
					</template>
				</template>
				<template #actions>
					<NcButton variant="tertiary" :disabled="isLoading" @click="isRemoveDialogOpen = false">
						{{ t('spreed', 'Dismiss') }}
					</NcButton>
					<NcButton variant="error" :disabled="isLoading || !!maxLengthWarning" @click="removeParticipant">
						{{ t('spreed', 'Remove') }}
					</NcButton>
				</template>
			</NcDialog>
		</template>
	</NcListItem>
</template>

<script>
import { showError, showSuccess } from '@nextcloud/dialogs'
import { emit } from '@nextcloud/event-bus'
import { t } from '@nextcloud/l10n'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcActionSeparator from '@nextcloud/vue/components/NcActionSeparator'
import NcActionText from '@nextcloud/vue/components/NcActionText'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcListItem from '@nextcloud/vue/components/NcListItem'
import NcTextArea from '@nextcloud/vue/components/NcTextArea'
import IconAccountMinusOutline from 'vue-material-design-icons/AccountMinusOutline.vue'
import IconAccountOutline from 'vue-material-design-icons/AccountOutline.vue'
import IconAccountPlusOutline from 'vue-material-design-icons/AccountPlusOutline.vue'
import IconBellOutline from 'vue-material-design-icons/BellOutline.vue'
import IconContentCopy from 'vue-material-design-icons/ContentCopy.vue'
import IconCrownOutline from 'vue-material-design-icons/CrownOutline.vue'
import IconDeleteOutline from 'vue-material-design-icons/DeleteOutline.vue'
import IconEmailOutline from 'vue-material-design-icons/EmailOutline.vue'
import IconHandBackLeftOutline from 'vue-material-design-icons/HandBackLeftOutline.vue'
import IconLockOpenVariantOutline from 'vue-material-design-icons/LockOpenVariantOutline.vue'
import IconLockOutline from 'vue-material-design-icons/LockOutline.vue'
import IconLockReset from 'vue-material-design-icons/LockReset.vue'
import IconMicrophoneOutline from 'vue-material-design-icons/MicrophoneOutline.vue'
import IconPencilOutline from 'vue-material-design-icons/PencilOutline.vue'
import IconPhoneDialOutline from 'vue-material-design-icons/PhoneDialOutline.vue'
import IconPhoneHangupOutline from 'vue-material-design-icons/PhoneHangupOutline.vue'
import IconPhoneInTalkOutline from 'vue-material-design-icons/PhoneInTalkOutline.vue'
import IconPhonePausedOutline from 'vue-material-design-icons/PhonePausedOutline.vue'
import IconTune from 'vue-material-design-icons/Tune.vue'
import IconVideoOutline from 'vue-material-design-icons/VideoOutline.vue'
import AvatarWrapper from '../../AvatarWrapper/AvatarWrapper.vue'
import DialpadPanel from '../../UIShared/DialpadPanel.vue'
import ParticipantPermissionsEditor from './ParticipantPermissionsEditor.vue'
import IconMicrophoneOffOutline from '../../../../img/material-icons/microphone-off-outline.svg?raw'
import { useGetToken } from '../../../composables/useGetToken.ts'
import { useIsInCall } from '../../../composables/useIsInCall.js'
import { ATTENDEE, CONVERSATION, PARTICIPANT, WEBINAR } from '../../../constants.ts'
import {
	callSIPDialOut,
	callSIPHangupPhone,
	callSIPHoldPhone,
	callSIPMutePhone,
	callSIPSendDTMF,
	callSIPUnmutePhone,
} from '../../../services/callsService.ts'
import { hasTalkFeature } from '../../../services/CapabilitiesManager.ts'
import { useActorStore } from '../../../stores/actor.ts'
import { formattedTime } from '../../../utils/formattedTime.ts'
import { getDisplayNameWithFallback } from '../../../utils/getDisplayName.ts'
import { readableNumber } from '../../../utils/readableNumber.ts'
import { getPreloadedUserStatus, getStatusMessage } from '../../../utils/userStatus.ts'

export default {
	name: 'Participant',

	components: {
		AvatarWrapper,
		DialpadPanel,
		NcActionButton,
		NcActionText,
		NcActionSeparator,
		NcButton,
		NcCheckboxRadioSwitch,
		NcDialog,
		NcIconSvgWrapper,
		NcListItem,
		NcTextArea,
		ParticipantPermissionsEditor,
		// Icons
		IconAccountOutline,
		IconAccountMinusOutline,
		IconAccountPlusOutline,
		IconBellOutline,
		IconContentCopy,
		IconCrownOutline,
		IconDeleteOutline,
		IconEmailOutline,
		IconHandBackLeftOutline,
		IconLockOutline,
		IconLockOpenVariantOutline,
		IconLockReset,
		IconMicrophoneOutline,
		IconPencilOutline,
		IconPhoneDialOutline,
		IconPhoneInTalkOutline,
		IconPhoneHangupOutline,
		IconPhonePausedOutline,
		IconTune,
		IconVideoOutline,
	},

	props: {
		participant: {
			type: Object,
			required: true,
		},

		/**
		 * Whether to show the user status on the avatar.
		 * This does not affect the status message row.
		 */
		showUserStatus: {
			type: Boolean,
			default: true,
		},
	},

	setup() {
		return {
			IconMicrophoneOffOutline,
			isInCall: useIsInCall(),
			actorStore: useActorStore(),
			token: useGetToken(),
		}
	},

	data() {
		return {
			permissionsEditor: false,
			isRemoveDialogOpen: false,
			isBanParticipant: false,
			internalNote: '',
			disabled: false,
			isLoading: false,
		}
	},

	computed: {
		participantNavigationId() {
			return this.participant.actorType + '_' + this.participant.actorId
		},

		participantSettingsAriaLabel() {
			return t('spreed', 'Settings for participant "{user}"', { user: this.computedName })
		},

		participantAriaLabel() {
			return t('spreed', 'Participant "{user}"', { user: this.computedName })
		},

		userNameTitle() {
			let text = this.computedName
			if (this.showModeratorLabel) {
				text += ' (' + t('spreed', 'moderator') + ')'
			}
			if (this.isBridgeBotUser) {
				text += ' (' + t('spreed', 'bot') + ')'
			}
			if (this.isGuestActor || this.isEmailActor) {
				text += ' (' + t('spreed', 'guest') + ')'
			}
			return text
		},

		isSpeakingStatusAvailable() {
			return this.isInCall && !!this.participant.inCall && !!this.timeSpeaking
		},

		phoneCallStatus() {
			if (!this.isPhoneActor || !this.participant.callId) {
				return undefined
			}
			return this.$store.getters.getPhoneStatus(this.participant.callId)
		},

		phoneMuteState() {
			if (!this.isPhoneActor || !this.participant.callId) {
				return undefined
			}
			switch (this.$store.getters.getPhoneMute(this.participant.callId)) {
				case PARTICIPANT.SIP_DIALOUT_FLAG.MUTE_MICROPHONE: {
					return 'muted'
				}
				case PARTICIPANT.SIP_DIALOUT_FLAG.MUTE_SPEAKER | PARTICIPANT.SIP_DIALOUT_FLAG.MUTE_MICROPHONE: {
					return 'hold'
				}
				case PARTICIPANT.SIP_DIALOUT_FLAG.NONE:
				default: {
					return undefined
				}
			}
		},

		statusMessage() {
			if (this.isInCall && this.phoneCallStatus) {
				switch (this.phoneCallStatus) {
					case 'ringing':
						return '📞 ' + t('spreed', 'Ringing …')
					case 'rejected':
						return '⚠️ ' + t('spreed', 'Call rejected')
					case 'accepted':
					case 'cleared':
						return ''
					case 'connected':
					default:
					// Fall through to show the talking time
						break
				}
			}

			if (this.isSpeakingStatusAvailable) {
				return this.isParticipantSpeaking
					? '💬 ' + t('spreed', '{time} talking …', { time: formattedTime(this.timeSpeaking, true) })
					: '💬 ' + t('spreed', '{time} talking time', { time: formattedTime(this.timeSpeaking, true) })
			}

			if (this.isEmailActor && this.participant?.invitedActorId) {
				return this.participant.invitedActorId
			}

			return getStatusMessage(this.participant)
		},

		isEmailActor() {
			return this.participant.actorType === ATTENDEE.ACTOR_TYPE.EMAILS
		},

		isPhoneActor() {
			return this.participant.actorType === ATTENDEE.ACTOR_TYPE.PHONES
		},

		isUserActor() {
			return this.participant.actorType === ATTENDEE.ACTOR_TYPE.USERS
		},

		isFederatedActor() {
			return this.participant.actorType === ATTENDEE.ACTOR_TYPE.FEDERATED_USERS
		},

		isGuestActor() {
			return this.participant.actorType === ATTENDEE.ACTOR_TYPE.GUESTS
		},

		canSendCallNotification() {
			return (this.isUserActor || this.isFederatedActor)
				&& !this.isSelf
				&& (this.currentParticipant.permissions & PARTICIPANT.PERMISSIONS.CALL_START) !== 0
				// Can also be undefined, so have to check > than disconnect
				&& this.currentParticipant.participantFlags > PARTICIPANT.CALL_FLAG.DISCONNECTED
				&& this.participant.inCall === PARTICIPANT.CALL_FLAG.DISCONNECTED
		},

		displayName() {
			return this.participant.displayName.trim()
		},

		computedName() {
			return getDisplayNameWithFallback(this.participant.displayName, this.participant.actorType)
		},

		attendeeId() {
			return this.participant.attendeeId
		},

		isHandRaised() {
			if (this.participant.inCall === PARTICIPANT.CALL_FLAG.DISCONNECTED) {
				return false
			}

			const raisedState = this.$store.getters.getParticipantRaisedHand(this.participant.sessionIds)
			return raisedState.state
		},

		callIcon() {
			if (this.participant.inCall === PARTICIPANT.CALL_FLAG.DISCONNECTED) {
				return null
			} else if (this.isHandRaised) {
				return { icon: IconHandBackLeftOutline, size: 18, title: t('spreed', 'Raised their hand') }
			} if (this.participant.inCall & PARTICIPANT.CALL_FLAG.WITH_VIDEO) {
				return { icon: IconVideoOutline, size: 20, title: t('spreed', 'Joined with video') }
			} else if (this.participant.inCall & PARTICIPANT.CALL_FLAG.WITH_PHONE) {
				return { icon: IconPhoneDialOutline, size: 20, title: t('spreed', 'Joined via phone') }
			} else {
				return { icon: IconMicrophoneOutline, size: 20, title: t('spreed', 'Joined with audio') }
			}
		},

		participantType() {
			return this.participant.participantType
		},

		sessionIds() {
			return this.participant.sessionIds || []
		},

		participantSpeakingInformation() {
			return this.$store.getters.getParticipantSpeakingInformation(this.attendeeId)
		},

		isParticipantSpeaking() {
			return this.participantSpeakingInformation?.speaking
		},

		attendeePin() {
			return this.canBeModerated && this.participant.attendeePin ? readableNumber(this.participant.attendeePin) : ''
		},

		currentParticipant() {
			return this.$store.getters.conversation(this.token) || {
				sessionId: '0',
				participantFlags: 0,
				participantType: this.actorStore.isLoggedIn ? PARTICIPANT.TYPE.USER : PARTICIPANT.TYPE.GUEST,
			}
		},

		conversation() {
			return this.$store.getters.conversation(this.token) || {
				type: CONVERSATION.TYPE.GROUP,
			}
		},

		isBridgeBotUser() {
			return this.participant.actorType === ATTENDEE.ACTOR_TYPE.USERS
				&& this.participant.actorId === ATTENDEE.BRIDGE_BOT_ID
		},

		isSelf() {
			return this.actorStore.checkIfSelfIsActor(this.participant)
		},

		selfIsModerator() {
			return this.participantTypeIsModerator(this.currentParticipant.participantType)
		},

		/**
		 * For now the user status is not overwriting the online-offline status anymore
		 * It felt too weird having users appear as offline but they are in the call or chat actively
		 * return this.participant.status === 'offline' ||  !this.sessionIds.length
		 */
		isOffline() {
			return !this.sessionIds.length && (this.isUserActor || this.isFederatedActor || this.isGuestActor)
				&& (hasTalkFeature(this.token, 'federation-v2') || (!this.conversation.remoteServer && !this.isFederatedActor))
		},

		isModerator() {
			return this.participantTypeIsModerator(this.participantType)
		},

		showBanOption() {
			return this.supportBanV1
				&& this.participant.actorType !== ATTENDEE.ACTOR_TYPE.FEDERATED_USERS
				&& this.showPermissionsOptions
		},

		showPermissionsOptions() {
			return this.canBeModerated
				&& !this.isModerator
				&& (this.participant.actorType === ATTENDEE.ACTOR_TYPE.USERS
					|| this.participant.actorType === ATTENDEE.ACTOR_TYPE.FEDERATED_USERS
					|| this.participant.actorType === ATTENDEE.ACTOR_TYPE.GUESTS
					|| this.participant.actorType === ATTENDEE.ACTOR_TYPE.EMAILS)
		},

		maxLengthWarning() {
			if (this.internalNote.length <= 4000) {
				return ''
			}
			return t('spreed', 'The text must be less than or equal to {maxLength} characters long. Your current text is {charactersCount} characters long.', {
				maxLength: 4000,
				charactersCount: this.internalNote.length,
			})
		},

		removeParticipantLabel() {
			switch (this.participant.actorType) {
				case ATTENDEE.ACTOR_TYPE.GROUPS:
					return t('spreed', 'Remove group and members')
				case ATTENDEE.ACTOR_TYPE.CIRCLES:
					return t('spreed', 'Remove team and members')
				case ATTENDEE.ACTOR_TYPE.USERS:
				default:
					return t('spreed', 'Remove participant')
			}
		},

		removeDialogMessage() {
			switch (this.participant.actorType) {
				case ATTENDEE.ACTOR_TYPE.GROUPS:
					return t('spreed', 'Do you really want to remove group "{displayName}" and its members from this conversation?', { displayName: this.computedName }, undefined, { escape: false, sanitize: false })
				case ATTENDEE.ACTOR_TYPE.CIRCLES:
					return t('spreed', 'Do you really want to remove team "{displayName}" and its members from this conversation?', { displayName: this.computedName }, undefined, { escape: false, sanitize: false })
				case ATTENDEE.ACTOR_TYPE.USERS:
				default:
					return t('spreed', 'Do you really want to remove {displayName} from this conversation?', { displayName: this.computedName }, undefined, { escape: false, sanitize: false })
			}
		},

		showModeratorLabel() {
			return this.isModerator
				&& ![CONVERSATION.TYPE.ONE_TO_ONE, CONVERSATION.TYPE.ONE_TO_ONE_FORMER, CONVERSATION.TYPE.CHANGELOG].includes(this.conversation.type)
		},

		canBeModerated() {
			return this.participantType !== PARTICIPANT.TYPE.OWNER
				&& !this.isSelf
				&& this.selfIsModerator
				&& !this.isBridgeBotUser
		},

		canBeDemoted() {
			return this.canBeModerated
				&& [PARTICIPANT.TYPE.MODERATOR, PARTICIPANT.TYPE.GUEST_MODERATOR].includes(this.participantType)
		},

		canBePromoted() {
			return this.canBeModerated
				&& !this.isModerator
				&& (this.participant.actorType === ATTENDEE.ACTOR_TYPE.USERS
					|| this.participant.actorType === ATTENDEE.ACTOR_TYPE.GUESTS
					|| this.participant.actorType === ATTENDEE.ACTOR_TYPE.EMAILS)
		},

		supportBanV1() {
			return hasTalkFeature(this.token, 'ban-v1')
		},

		isLobbyEnabled() {
			return this.conversation.lobbyState === WEBINAR.LOBBY.NON_MODERATORS
		},

		canSkipLobby() {
			return this.isModerator || (this.participant.permissions & PARTICIPANT.PERMISSIONS.LOBBY_IGNORE) !== 0
		},

		showToggleLobbyAction() {
			return this.canBeModerated && !this.isModerator && this.isLobbyEnabled
		},

		showParticipantActions() {
			return this.canBeModerated || this.canSendCallNotification
		},

		preloadedUserStatus() {
			return getPreloadedUserStatus(this.participant)
		},

		attendeePermissions() {
			return this.participant.attendeePermissions
		},

		hasNonDefaultPermissions() {
			return this.attendeePermissions !== PARTICIPANT.PERMISSIONS.DEFAULT
		},

		actionIcon() {
			if (this.isModerator) {
				return undefined
			}

			if (this.attendeePermissions === PARTICIPANT.PERMISSIONS.MAX_CUSTOM) {
				return IconLockOpenVariantOutline
			} else if (this.attendeePermissions === PARTICIPANT.PERMISSIONS.CUSTOM) {
				return IconLockOutline
			} else if (this.attendeePermissions !== PARTICIPANT.PERMISSIONS.DEFAULT) {
				return IconTune
			}
			return undefined
		},

		timeSpeaking() {
			if (!this.participantSpeakingInformation || this.isParticipantSpeaking === undefined) {
				return 0
			}

			return this.participantSpeakingInformation.totalCountedTime
		},
	},

	watch: {
		phoneCallStatus(value) {
			if (!value || !(value === 'ringing' || value === 'accepted')) {
				this.disabled = false
			}
		},
	},

	methods: {
		t,

		formattedTime,

		participantTypeIsModerator(participantType) {
			return [PARTICIPANT.TYPE.OWNER, PARTICIPANT.TYPE.MODERATOR, PARTICIPANT.TYPE.GUEST_MODERATOR].includes(participantType)
		},

		async promoteToModerator() {
			await this.$store.dispatch('promoteToModerator', {
				token: this.token,
				attendeeId: this.attendeeId,
			})
		},

		async demoteFromModerator() {
			await this.$store.dispatch('demoteFromModerator', {
				token: this.token,
				attendeeId: this.attendeeId,
			})
		},

		async resendInvitation() {
			await this.$store.dispatch('resendInvitations', {
				token: this.token,
				attendeeId: this.attendeeId,
				actorId: this.participant.invitedActorId ?? this.participant.actorId,
			})
		},

		async sendCallNotification() {
			try {
				await this.$store.dispatch('sendCallNotification', {
					token: this.token,
					attendeeId: this.attendeeId,
				})
				showSuccess(t('spreed', 'Notification was sent to {displayName}', { displayName: this.computedName }))
			} catch (error) {
				console.error(error)
				showError(t('spreed', 'Could not send notification to {displayName}', { displayName: this.computedName }))
			}
		},

		async removeParticipant() {
			this.isLoading = true
			try {
				await this.$store.dispatch('removeParticipant', {
					token: this.token,
					attendeeId: this.attendeeId,
					banParticipant: this.isBanParticipant,
					internalNote: this.internalNote,
				})
				this.isBanParticipant = false
				this.internalNote = ''
				this.isRemoveDialogOpen = false
			} catch (error) {
				console.error('Error while removing the participant: ', error)
			} finally {
				this.isLoading = false
			}
		},

		grantAllPermissions() {
			try {
				this.$store.dispatch('grantAllPermissionsToParticipant', { token: this.token, attendeeId: this.attendeeId })
				showSuccess(t('spreed', 'Permissions granted to {displayName}', { displayName: this.computedName }))
			} catch (error) {
				showError(t('spreed', 'Could not modify permissions for {displayName}', { displayName: this.computedName }))
			}
		},

		removeAllPermissions() {
			try {
				this.$store.dispatch('removeAllPermissionsFromParticipant', { token: this.token, attendeeId: this.attendeeId })
				showSuccess(t('spreed', 'Permissions removed for {displayName}', { displayName: this.computedName }))
			} catch (error) {
				showError(t('spreed', 'Could not modify permissions for {displayName}', { displayName: this.computedName }))
			}
		},

		applyDefaultPermissions() {
			try {
				this.$store.dispatch('setPermissions', { token: this.token, attendeeId: this.attendeeId, permissions: PARTICIPANT.PERMISSIONS.DEFAULT })
				showSuccess(t('spreed', 'Permissions set to default for {displayName}', { displayName: this.computedName }))
			} catch (error) {
				showError(t('spreed', 'Could not modify permissions for {displayName}', { displayName: this.computedName }))
			}
		},

		async setLobbyPermission(value) {
			try {
				await this.$store.dispatch('setPermissions', {
					token: this.token,
					attendeeId: this.attendeeId,
					method: value ? 'add' : 'remove',
					permissions: PARTICIPANT.PERMISSIONS.LOBBY_IGNORE,
				})
				if (value) {
					showSuccess(t('spreed', 'Permissions granted to {displayName}', { displayName: this.computedName }))
				} else {
					showSuccess(t('spreed', 'Permissions removed for {displayName}', { displayName: this.computedName }))
				}
			} catch (error) {
				showError(t('spreed', 'Could not modify permissions for {displayName}', { displayName: this.computedName }))
			}
		},

		async dialOutPhoneNumber() {
			try {
				this.disabled = true
				if (!this.isInCall) {
					let flags = PARTICIPANT.CALL_FLAG.IN_CALL
					flags |= PARTICIPANT.CALL_FLAG.WITH_AUDIO

					// Close navigation
					emit('toggle-navigation', { open: false })
					console.info('Joining call')
					await this.$store.dispatch('joinCall', {
						token: this.token,
						participantIdentifier: this.actorStore.participantIdentifier,
						flags,
						silent: false,
						recordingConsent: true,
					})
				}
				await callSIPDialOut(this.token, this.participant.attendeeId)
			} catch (error) {
				this.disabled = false
				if (error?.response?.data?.ocs?.data?.message) {
					showError(t('spreed', 'Phone number could not be called: {error}', {
						error: error?.response?.data?.ocs?.data?.message,
					}))
				} else {
					console.error(error)
					showError(t('spreed', 'Phone number could not be called'))
				}
			}
		},

		async hangupPhoneNumber() {
			try {
				this.disabled = true
				await callSIPHangupPhone(this.sessionIds[0])
			} catch (error) {
				showError(t('spreed', 'Phone number could not be hung up'))
				this.disabled = false
			}
		},

		async holdPhoneNumber() {
			try {
				await callSIPHoldPhone(this.sessionIds[0])
				this.$store.dispatch('setPhoneMute', {
					callid: this.participant.callId,
					value: PARTICIPANT.SIP_DIALOUT_FLAG.MUTE_MICROPHONE | PARTICIPANT.SIP_DIALOUT_FLAG.MUTE_SPEAKER,
				})
			} catch (error) {
				showError(t('spreed', 'Phone number could not be put on hold'))
			}
		},

		async mutePhoneNumber() {
			try {
				await callSIPMutePhone(this.sessionIds[0])
				this.$store.dispatch('setPhoneMute', {
					callid: this.participant.callId,
					value: PARTICIPANT.SIP_DIALOUT_FLAG.MUTE_MICROPHONE,
				})
			} catch (error) {
				showError(t('spreed', 'Phone number could not be muted'))
			}
		},

		async unmutePhoneNumber() {
			try {
				await callSIPUnmutePhone(this.sessionIds[0])
				this.$store.dispatch('setPhoneMute', {
					callid: this.participant.callId,
					value: PARTICIPANT.SIP_DIALOUT_FLAG.NONE,
				})
			} catch (error) {
				showError(t('spreed', 'Phone number could not be unmuted'))
			}
		},

		async dialType(value) {
			try {
				await callSIPSendDTMF(this.sessionIds[0], value)
			} catch (error) {
				showError(t('spreed', 'DTMF message could not be sent'))
			}
		},

		async copyPhoneNumber() {
			try {
				await navigator.clipboard.writeText(this.participant.phoneNumber)
				showSuccess(t('spreed', 'Phone number copied to clipboard'))
			} catch (error) {
				showError(t('spreed', 'Phone number could not be copied'))
			}
		},
	},
}
</script>

<style lang="scss" scoped>
.participant {
	line-height: 20px;

	// Overwrite NcListItem styles
	:deep(.list-item) {
		overflow: hidden;
		outline-offset: -2px;
		cursor: default;

		a, a * {
			cursor: default;
		}

		button, button * {
			cursor: pointer;
		}

		.avatardiv .avatardiv__user-status {
			inset-inline-end: -2px !important;
			bottom: -2px !important;
		}
	}

	&--offline &__user-name {
		color: var(--color-text-maxcontrast);
	}

	&__user-badge {
		color: var(--color-text-maxcontrast);
		font-weight: 300;
		padding-inline-start: var(--default-grid-baseline);
	}

	&__user {
		overflow: hidden;
		text-overflow: ellipsis;
		white-space: nowrap;
	}

	&__status {
		&--highlighted {
			font-weight: bold;
		}
	}

	&__call-state {
		height: 100%;
		display: flex;
		align-items: center;
		color: var(--color-text-maxcontrast);
	}

	:deep(.list-item-content__actions > .critical) {
		color: var(--color-error);
	}
}

.participant-dialog {
	&__input {
		margin-block-end: 6px;
	}
}

.critical > :deep(.action-button) {
	color: var(--color-error);
}

</style>
