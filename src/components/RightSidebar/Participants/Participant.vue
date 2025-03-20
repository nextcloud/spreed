<!--
  - @copyright Copyright (c) 2019 Joas Schilling <coding@schilljs.com>
  -
  - @author Joas Schilling <coding@schilljs.com>
  -
  - @license AGPL-3.0-or-later
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
-->

<template>
	<component :is="tag"
		:data-nav-id="participantNavigationId"
		class="participant-row"
		:class="{
			'offline': isOffline,
			'currentUser': isSelf,
			'guestUser': isGuest,
			'isSearched': isSearched,
			'selected': isSelected }"
		:aria-label="participantAriaLabel"
		:role="isSearched ? 'listitem' : undefined"
		:tabindex="0"
		v-on="isSearched ? { click: handleClick, 'keydown.enter': handleClick } : {}"
		@keydown.enter="handleClick">
		<!-- Participant's avatar -->
		<AvatarWrapper :id="computedId"
			:token="isSearched ? 'new' : token"
			:name="computedName"
			:source="participant.source || participant.actorType"
			:disable-menu="isSearched"
			disable-tooltip
			:show-user-status="showUserStatus"
			:preloaded-user-status="preloadedUserStatus"
			:highlighted="isSpeakingStatusAvailable && isParticipantSpeaking"
			:offline="isOffline" />

		<!-- Participant's data -->
		<div class="participant-row__user-wrapper"
			:class="{
				'has-call-icon': callIcon,
				'has-menu-icon': (canBeModerated || canSendCallNotification) && !isSearched
			}">
			<!-- First line: participant's name and type -->
			<div ref="userName"
				class="participant-row__user-descriptor"
				@mouseover="updateUserNameNeedsTooltip()">
				<span v-tooltip.auto="userTooltipText"
					class="participant-row__user-name">{{ computedName }}</span>
				<span v-if="showModeratorLabel" class="participant-row__moderator-indicator">({{ t('spreed', 'moderator') }})</span>
				<span v-if="isBridgeBotUser" class="participant-row__moderator-indicator">({{ t('spreed', 'bot') }})</span>
				<span v-if="isGuest" class="participant-row__guest-indicator">({{ t('spreed', 'guest') }})</span>
				<span v-if="!isSelf && isLobbyEnabled && !canSkipLobby" class="participant-row__guest-indicator">({{ t('spreed', 'in the lobby') }})</span>
			</div>

			<!-- Second line: participant status message if applicable -->
			<div v-if="isSearched && shareWithDisplayNameUnique"
				class="participant-row__status">
				<span>{{ shareWithDisplayNameUnique }}</span>
			</div>
			<div v-else-if="statusMessage"
				ref="statusMessage"
				class="participant-row__status"
				:class="{'participant-row__status--highlighted': isParticipantSpeaking}"
				@mouseover="updateStatusNeedsTooltip()">
				<span v-tooltip.auto="statusMessageTooltip">{{ statusMessage }}</span>
			</div>
		</div>

		<!-- Phone participant dial action -->
		<div v-if="isInCall && canBeModerated && isPhoneActor"
			:id="participantNavigationId"
			class="participant-row__dial-actions">
			<NcButton v-if="!participant.inCall"
				type="success"
				:aria-label="t('spreed', 'Dial out phone')"
				:title="t('spreed', 'Dial out phone')"
				:disabled="disabled"
				@click="dialOutPhoneNumber">
				<template #icon>
					<Phone :size="20" />
				</template>
			</NcButton>
			<template v-else>
				<NcButton type="error"
					:aria-label="t('spreed', 'Hang up phone')"
					:title="t('spreed', 'Hang up phone')"
					:disabled="disabled"
					@click="hangupPhoneNumber">
					<template #icon>
						<PhoneHangup :size="20" />
					</template>
				</NcButton>
				<DialpadPanel :disabled="disabled"
					container="#tab-participants"
					dialing
					@dial:type="dialType" />
			</template>
		</div>

		<!-- Call state icon -->
		<div v-else-if="callIcon"
			v-tooltip.auto="callIconTooltip"
			class="participant-row__callstate-icon">
			<span class="hidden-visually">{{ callIconTooltip }}</span>
			<Microphone v-if="callIcon === 'audio'" :size="20" />
			<Phone v-if="callIcon === 'phone'" :size="20" />
			<VideoIcon v-if="callIcon === 'video'" :size="20" />
			<!-- Icon is visually bigger, so we reduce its size -->
			<HandBackLeft v-if="callIcon === 'hand'" :size="18" />
		</div>

		<!-- Participant's actions menu -->
		<NcActions v-if="(canBeModerated || canSendCallNotification) && !isSearched"
			:container="container"
			:aria-label="participantSettingsAriaLabel"
			:inline="showToggleLobbyAction ? 1 : 0"
			:force-menu="!showToggleLobbyAction"
			placement="bottom-end"
			class="participant-row__actions">
			<template #icon>
				<LockOpenVariant v-if="actionIcon === 'LockOpenVariant'"
					:size="20" />
				<Lock v-else-if="actionIcon === 'Lock'"
					:size="20" />
				<Tune v-else-if="actionIcon === 'Tune'"
					:size="20" />
				<DotsHorizontal v-else
					:size="20" />
			</template>

			<!-- Information and rights -->
			<NcActionText v-if="attendeePin" :name="t('spreed', 'Dial-in PIN')">
				<template #icon>
					<Lock :size="20" />
				</template>
				{{ attendeePin }}
			</NcActionText>
			<!-- Grant or revoke lobby permissions (inline button) -->
			<template v-if="showToggleLobbyAction">
				<NcActionButton v-if="canSkipLobby"
					key="lobby-permission-skip"
					close-after-click
					@click="setLobbyPermission(false)">
					<template #icon>
						<AccountMinusIcon :size="20" />
					</template>
					{{ t('spreed', 'Move back to lobby') }}
				</NcActionButton>
				<NcActionButton v-else
					key="lobby-permission-join"
					close-after-click
					@click="setLobbyPermission(true)">
					<template #icon>
						<AccountPlusIcon :size="20" />
					</template>
					{{ t('spreed', 'Move to conversation') }}
				</NcActionButton>
			</template>
			<!-- Grant or revoke moderator permissions -->
			<NcActionButton v-if="canBeDemoted"
				key="demote-moderator"
				close-after-click
				@click="demoteFromModerator">
				<template #icon>
					<Account :size="20" />
				</template>
				{{ t('spreed', 'Demote from moderator') }}
			</NcActionButton>
			<NcActionButton v-else-if="canBePromoted"
				key="promote-moderator"
				close-after-click
				@click="promoteToModerator">
				<template #icon>
					<Crown :size="20" />
				</template>
				{{ t('spreed', 'Promote to moderator') }}
			</NcActionButton>
			<NcActionButton v-if="canBeModerated && isEmailActor"
				key="resend-invitation"
				close-after-click
				@click="resendInvitation">
				<template #icon>
					<Email :size="20" />
				</template>
				{{ t('spreed', 'Resend invitation') }}
			</NcActionButton>
			<NcActionButton v-if="canSendCallNotification"
				key="send-call-notification"
				close-after-click
				@click="sendCallNotification">
				<template #icon>
					<Bell :size="20" />
				</template>
				{{ t('spreed', 'Send call notification') }}
			</NcActionButton>
			<template v-if="canBeModerated && isPhoneActor">
				<NcActionButton v-if="!conversation.hasCall && !isInCall && !participant.callId"
					key="dial-out-phone-number"
					close-after-click
					@click="dialOutPhoneNumber">
					<template #icon>
						<Phone :size="20" />
					</template>
					{{ t('spreed', 'Dial out phone number') }}
				</NcActionButton>
				<template v-else-if="isInCall && participant.callId">
					<NcActionButton v-if="phoneMuteState === 'hold'"
						key="resume-call-phone-number"
						close-after-click
						@click="unmutePhoneNumber">
						<template #icon>
							<PhoneInTalk :size="20" />
						</template>
						{{ t('spreed', 'Resume call for phone number') }}
					</NcActionButton>
					<template v-else>
						<NcActionButton key="hold-call-phone-number"
							close-after-click
							@click="holdPhoneNumber">
							<template #icon>
								<PhonePaused :size="20" />
							</template>
							{{ t('spreed', 'Put phone number on hold') }}
						</NcActionButton>
						<NcActionButton v-if="phoneMuteState === 'muted'"
							key="unmute-call-phone-number"
							close-after-click
							@click="unmutePhoneNumber">
							<template #icon>
								<Microphone :size="20" />
							</template>
							{{ t('spreed', 'Unmute phone number') }}
						</NcActionButton>
						<NcActionButton v-else
							key="mute-call-phone-number"
							close-after-click
							@click="mutePhoneNumber">
							<template #icon>
								<MicrophoneOff :size="20" />
							</template>
							{{ t('spreed', 'Mute phone number') }}
						</NcActionButton>
					</template>
				</template>
				<NcActionButton key="copy-phone-number"
					close-after-click
					@click="copyPhoneNumber">
					<template #icon>
						<ContentCopy :size="20" />
					</template>
					{{ t('spreed', 'Copy phone number') }}
				</NcActionButton>
			</template>

			<!-- Permissions -->
			<template v-if="showPermissionsOptions">
				<NcActionSeparator />
				<NcActionButton v-if="hasNonDefaultPermissions"
					key="reset-permissions"
					close-after-click
					@click="applyDefaultPermissions">
					<template #icon>
						<LockReset :size="20" />
					</template>
					{{ t('spreed', 'Reset custom permissions') }}
				</NcActionButton>
				<NcActionButton key="grant-all-permissions"
					close-after-click
					@click="grantAllPermissions">
					<template #icon>
						<LockOpenVariant :size="20" />
					</template>
					{{ t('spreed', 'Grant all permissions') }}
				</NcActionButton>
				<NcActionButton key="remove-all-permissions"
					close-after-click
					@click="removeAllPermissions">
					<template #icon>
						<Lock :size="20" />
					</template>
					{{ t('spreed', 'Remove all permissions') }}
				</NcActionButton>
				<NcActionButton key="edit-permissions"
					close-after-click
					@click="showPermissionsEditor">
					<template #icon>
						<Pencil :size="20" />
					</template>
					{{ t('spreed', 'Edit permissions') }}
				</NcActionButton>
			</template>

			<!-- Remove -->
			<NcActionSeparator v-if="canBeModerated && showPermissionsOptions" />
			<NcActionButton v-if="canBeModerated"
				key="remove-participant"
				close-after-click
				@click="removeParticipant">
				<template #icon>
					<Delete :size="20" />
				</template>
				{{ removeParticipantLabel }}
			</NcActionButton>
		</NcActions>

		<ParticipantPermissionsEditor v-if="permissionsEditor"
			:actor-id="participant.actorId"
			close-after-click
			:participant="participant"
			:token="token"
			@close="hidePermissionsEditor" />

		<!-- Checkmark in case the current participant is selected -->
		<div v-if="isSelected" class="icon-checkmark participant-row__utils utils__checkmark" />
	</component>
</template>

<script>
import { inject } from 'vue'

import Account from 'vue-material-design-icons/Account.vue'
import AccountMinusIcon from 'vue-material-design-icons/AccountMinus.vue'
import AccountPlusIcon from 'vue-material-design-icons/AccountPlus.vue'
import Bell from 'vue-material-design-icons/Bell.vue'
import ContentCopy from 'vue-material-design-icons/ContentCopy.vue'
import Crown from 'vue-material-design-icons/Crown.vue'
import Delete from 'vue-material-design-icons/Delete.vue'
import DotsHorizontal from 'vue-material-design-icons/DotsHorizontal.vue'
import Email from 'vue-material-design-icons/Email.vue'
import HandBackLeft from 'vue-material-design-icons/HandBackLeft.vue'
import Lock from 'vue-material-design-icons/Lock.vue'
import LockOpenVariant from 'vue-material-design-icons/LockOpenVariant.vue'
import LockReset from 'vue-material-design-icons/LockReset.vue'
import Microphone from 'vue-material-design-icons/Microphone.vue'
import MicrophoneOff from 'vue-material-design-icons/MicrophoneOff.vue'
import Pencil from 'vue-material-design-icons/Pencil.vue'
import Phone from 'vue-material-design-icons/Phone.vue'
import PhoneHangup from 'vue-material-design-icons/PhoneHangup.vue'
import PhoneInTalk from 'vue-material-design-icons/PhoneInTalk.vue'
import PhonePaused from 'vue-material-design-icons/PhonePaused.vue'
import Tune from 'vue-material-design-icons/Tune.vue'
import VideoIcon from 'vue-material-design-icons/Video.vue'

import { getCapabilities } from '@nextcloud/capabilities'
import { showError, showSuccess } from '@nextcloud/dialogs'
import { emit } from '@nextcloud/event-bus'

import NcActionButton from '@nextcloud/vue/dist/Components/NcActionButton.js'
import NcActions from '@nextcloud/vue/dist/Components/NcActions.js'
import NcActionSeparator from '@nextcloud/vue/dist/Components/NcActionSeparator.js'
import NcActionText from '@nextcloud/vue/dist/Components/NcActionText.js'
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'

import ParticipantPermissionsEditor from './ParticipantPermissionsEditor.vue'
import AvatarWrapper from '../../AvatarWrapper/AvatarWrapper.vue'
import DialpadPanel from '../../UIShared/DialpadPanel.vue'

import { useIsInCall } from '../../../composables/useIsInCall.js'
import { CONVERSATION, PARTICIPANT, ATTENDEE, WEBINAR } from '../../../constants.js'
import {
	callSIPDialOut,
	callSIPHangupPhone,
	callSIPHoldPhone,
	callSIPMutePhone,
	callSIPUnmutePhone,
	callSIPSendDTMF,
} from '../../../services/callsService.js'
import { formattedTime } from '../../../utils/formattedTime.ts'
import { readableNumber } from '../../../utils/readableNumber.ts'
import { getStatusMessage } from '../../../utils/userStatus.js'

const supportFederationV1 = getCapabilities()?.spreed?.features?.includes('federation-v1')

export default {
	name: 'Participant',

	components: {
		AvatarWrapper,
		DialpadPanel,
		NcActions,
		NcActionButton,
		NcActionText,
		NcActionSeparator,
		NcButton,
		ParticipantPermissionsEditor,
		// Icons
		Account,
		AccountMinusIcon,
		AccountPlusIcon,
		Bell,
		ContentCopy,
		Crown,
		Delete,
		DotsHorizontal,
		Email,
		HandBackLeft,
		Lock,
		LockOpenVariant,
		LockReset,
		Microphone,
		MicrophoneOff,
		Pencil,
		Phone,
		PhoneInTalk,
		PhoneHangup,
		PhonePaused,
		Tune,
		VideoIcon,
	},

	props: {
		tag: {
			type: String,
			default: 'li',
		},

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

	emits: ['click-participant'],

	setup() {
		const isInCall = useIsInCall()
		const selectedParticipants = inject('selectedParticipants', [])

		// Toggles the bulk selection state of this component
		const isSelectable = inject('bulkParticipantsSelection', false)

		return {
			isInCall,
			selectedParticipants,
			isSelectable,
		}
	},

	data() {
		return {
			isUserNameTooltipVisible: false,
			isStatusTooltipVisible: false,
			permissionsEditor: false,
			disabled: false,
		}
	},

	computed: {
		container() {
			return this.$store.getters.getMainContainerSelector()
		},

		participantNavigationId() {
			if (this.participant.actorType && this.participant.actorId) {
				return this.participant.actorType + '_' + this.participant.actorId
			} else {
				return this.participant.source + '_' + this.participant.id
			}
		},

		participantSettingsAriaLabel() {
			return t('spreed', 'Settings for participant "{user}"', { user: this.computedName })
		},

		participantAriaLabel() {
			if (this.isSearched) {
				return t('spreed', 'Add participant "{user}"', { user: this.computedName })
			} else {
				return t('spreed', 'Participant "{user}"', { user: this.computedName })
			}
		},

		userTooltipText() {
			if (!this.isUserNameTooltipVisible) {
				return false
			}
			let text = this.computedName
			if (this.showModeratorLabel) {
				text += ' (' + t('spreed', 'moderator') + ')'
			}
			if (this.isBridgeBotUser) {
				text += ' (' + t('spreed', 'bot') + ')'
			}
			if (this.isGuest) {
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

			return getStatusMessage(this.participant)
		},

		statusMessageTooltip() {
			if (!this.isStatusTooltipVisible) {
				return false
			}

			return this.statusMessage
		},

		/**
		 * Check if the current participant belongs to the selected participants array
		 * in the store
		 *
		 * @return {boolean}
		 */
		isSelected() {
			return this.isSelectable
				? this.selectedParticipants.some(selected => {
					return selected.id === this.participant.id && selected.source === this.participant.source
				})
				: false
		},

		/**
		 * If the Participant component is used as to display a search result, it will
		 * return true. We use this not to display actions on the searched contacts and
		 * groups.
		 *
		 * @return {boolean}
		 */
		isSearched() {
			return this.participant.label !== undefined
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
			return this.isUserActor
				&& !this.isSelf
				&& (this.currentParticipant.permissions & PARTICIPANT.PERMISSIONS.CALL_START) !== 0
				// Can also be undefined, so have to check > than disconnect
				&& this.currentParticipant.participantFlags > PARTICIPANT.CALL_FLAG.DISCONNECTED
				&& this.participant.inCall === PARTICIPANT.CALL_FLAG.DISCONNECTED
		},

		computedName() {
			if (!this.isSearched) {
				const displayName = this.participant.displayName.trim()

				if (displayName === '' && this.isGuest) {
					return t('spreed', 'Guest')
				}

				if (displayName === '') {
					return t('spreed', 'Deleted user')
				}

				return displayName
			}
			return this.participant.label
		},

		computedId() {
			if (!this.isSearched) {
				return this.participant.actorId
			}
			return this.participant.id
		},

		attendeeId() {
			return this.participant.attendeeId
		},

		shareWithDisplayNameUnique() {
			return this.participant.shareWithDisplayNameUnique
		},

		isHandRaised() {
			if (this.isSearched || this.participant.inCall === PARTICIPANT.CALL_FLAG.DISCONNECTED) {
				return false
			}

			const raisedState = this.$store.getters.getParticipantRaisedHand(this.participant.sessionIds)
			return raisedState.state
		},

		callIcon() {
			if (this.isSearched || this.participant.inCall === PARTICIPANT.CALL_FLAG.DISCONNECTED) {
				return ''
			}
			if (this.isHandRaised) {
				return 'hand'
			}
			const withVideo = this.participant.inCall & PARTICIPANT.CALL_FLAG.WITH_VIDEO
			if (withVideo) {
				return 'video'
			}
			const withPhone = this.participant.inCall & PARTICIPANT.CALL_FLAG.WITH_PHONE
			if (withPhone) {
				return 'phone'
			}
			return 'audio'
		},

		callIconTooltip() {
			if (this.callIcon === 'audio') {
				return t('spreed', 'Joined with audio')
			} else if (this.callIcon === 'video') {
				return t('spreed', 'Joined with video')
			} else if (this.callIcon === 'phone') {
				return t('spreed', 'Joined via phone')
			} else if (this.callIcon === 'hand') {
				return t('spreed', 'Raised their hand')
			}
			return null
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

		token() {
			return this.$store.getters.getToken()
		},

		currentParticipant() {
			return this.$store.getters.conversation(this.token) || {
				sessionId: '0',
				participantFlags: 0,
				participantType: this.$store.getters.getUserId() !== null ? PARTICIPANT.TYPE.USER : PARTICIPANT.TYPE.GUEST,
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
			return this.sessionIds.length && this.sessionIds.includes(this.currentParticipant.sessionId)
		},

		selfIsModerator() {
			return this.participantTypeIsModerator(this.currentParticipant.participantType)
		},

		/**
		 * For now the user status is not overwriting the online-offline status anymore
		 * It felt too weird having users appear as offline but they are in the call or chat actively
		 * return this.participant.status === 'offline' ||  !this.sessionIds.length && !this.isSearched
		 */
		isOffline() {
			return !this.sessionIds.length && !this.isSearched
				&& (this.isUserActor || this.isFederatedActor || this.isGuestActor)
				&& (!supportFederationV1 || (!this.conversation.remoteServer && !this.isFederatedActor))
		},

		isGuest() {
			return [PARTICIPANT.TYPE.GUEST, PARTICIPANT.TYPE.GUEST_MODERATOR].includes(this.participantType)
		},

		isModerator() {
			return this.participantTypeIsModerator(this.participantType)
		},

		showPermissionsOptions() {
			return this.canBeModerated
				&& !this.isModerator
				&& (this.participant.actorType === ATTENDEE.ACTOR_TYPE.USERS
					|| this.participant.actorType === ATTENDEE.ACTOR_TYPE.GUESTS
					|| this.participant.actorType === ATTENDEE.ACTOR_TYPE.EMAILS)
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

		isLobbyEnabled() {
			return this.conversation.lobbyState === WEBINAR.LOBBY.NON_MODERATORS
		},

		canSkipLobby() {
			return this.isModerator || (this.participant.permissions & PARTICIPANT.PERMISSIONS.LOBBY_IGNORE) !== 0
		},

		showToggleLobbyAction() {
			return this.canBeModerated && !this.isModerator && this.isLobbyEnabled
		},

		preloadedUserStatus() {
			if (Object.prototype.hasOwnProperty.call(this.participant, 'statusMessage')) {
				// We preloaded the status when via participants API
				return {
					status: this.participant.status || null,
					message: this.participant.statusMessage || null,
					icon: this.participant.statusIcon || null,
				}
			}
			if (Object.prototype.hasOwnProperty.call(this.participant, 'status')) {
				// We preloaded the status when via search API
				return {
					status: this.participant.status.status || null,
					message: this.participant.status.message || null,
					icon: this.participant.status.icon || null,
				}
			}
			return undefined
		},

		attendeePermissions() {
			return this.participant.attendeePermissions
		},

		hasNonDefaultPermissions() {
			return this.attendeePermissions !== PARTICIPANT.PERMISSIONS.DEFAULT
		},

		actionIcon() {
			if (this.isModerator) {
				return ''
			}

			if (this.attendeePermissions === PARTICIPANT.PERMISSIONS.MAX_CUSTOM) {
				return 'LockOpenVariant'
			} else if (this.attendeePermissions === PARTICIPANT.PERMISSIONS.CUSTOM) {
				return 'Lock'
			} else if (this.attendeePermissions !== PARTICIPANT.PERMISSIONS.DEFAULT) {
				return 'Tune'
			}
			return ''
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
		}
	},

	methods: {
		formattedTime,

		updateUserNameNeedsTooltip() {
			// check if ellipsized
			const e = this.$refs.userName
			this.isUserNameTooltipVisible = (e && e.offsetWidth < e.scrollWidth)
		},

		updateStatusNeedsTooltip() {
			// check if ellipsized
			const e = this.$refs.statusMessage
			this.isStatusTooltipVisible = (e && e.offsetWidth < e.scrollWidth)
		},

		// Used to allow selecting participants in a search.
		handleClick() {
			if (this.isSearched) {
				this.$emit('click-participant', this.participant)
			}
		},

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
			try {
				await this.$store.dispatch('resendInvitations', {
					token: this.token,
					attendeeId: this.attendeeId,
				})
				showSuccess(t('spreed', 'Invitation was sent to {actorId}', { actorId: this.participant.actorId }))
			} catch (error) {
				showError(t('spreed', 'Could not send invitation to {actorId}', { actorId: this.participant.actorId }))
			}
		},

		async sendCallNotification() {
			try {
				await this.$store.dispatch('sendCallNotification', {
					token: this.token,
					attendeeId: this.attendeeId,
				})
				showSuccess(t('spreed', 'Notification was sent to {displayName}', { displayName: this.participant.displayName }))
			} catch (error) {
				console.error(error)
				showError(t('spreed', 'Could not send notification to {displayName}', { displayName: this.participant.displayName }))
			}
		},

		async removeParticipant() {
			await this.$store.dispatch('removeParticipant', {
				token: this.token,
				attendeeId: this.attendeeId,
			})
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

		showPermissionsEditor() {
			this.permissionsEditor = true
		},

		hidePermissionsEditor() {
			this.permissionsEditor = false
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
						participantIdentifier: this.$store.getters.getParticipantIdentifier(),
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
						error: error?.response?.data?.ocs?.data?.message
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
.selected {
	background-color: var(--color-primary-element-light);
	border-radius: 5px;
}

.participant-row {
	display: flex;
	align-items: center;
	cursor: default;
	margin: 4px 0;
	border-radius: var(--border-radius-pill);
	height: 56px;
	padding: 0 4px;

	&.isSearched {
		cursor: pointer;
	}

	&__user-wrapper {
		margin-top: -4px;
		margin-left: 12px;
		width: calc(100% - 100px);
		display: flex;
		flex-direction: column;

		&.has-call-icon {
			/** reduce text width to have some distance from call icon */
			padding-right: 5px;
			/** call icon on the right most column */
			width: calc(100% - 90px);
		}

		&.has-call-icon.has-menu-icon {
			/** make room for the call icon + menu icon */
			width: calc(100% - 124px);
		}
	}
	&__user-name,
	&__guest-indicator,
	&__moderator-indicator {
		vertical-align: middle;
		line-height: normal;
	}
	&__guest-indicator,
	&__moderator-indicator {
		color: var(--color-text-maxcontrast);
		font-weight: 300;
		padding-left: 5px;
	}
	&__user-descriptor {
		overflow: hidden;
		text-overflow: ellipsis;
		white-space: nowrap;
	}
	&__status {
		color: var(--color-text-maxcontrast);
		line-height: 1.3em;
		overflow: hidden;
		text-overflow: ellipsis;
		white-space: nowrap;
		&--highlighted {
			font-weight: bold;
		}
	}
	&__icon {
		width: 44px;
		height: 44px;
		cursor: pointer;
	}
	&__utils {
		margin-right: 28px;
	}

	&__callstate-icon {
		opacity: .4;
		display: flex;
		align-items: center;
	}

	&__dial-actions {
		display: flex;
		gap: 4px;
	}

	&:focus,
	&:focus-visible {
		z-index: 1;
		outline: 2px solid var(--color-primary-element);
	}
}

.participant-row.isSearched .participant-row__user-name {
	cursor: pointer;
}

.utils {
	&__checkmark {
		margin-right: 11px;
	}
}

.offline {

	.participant-row__user-descriptor > span {
		color: var(--color-text-maxcontrast);
	}
}

</style>
