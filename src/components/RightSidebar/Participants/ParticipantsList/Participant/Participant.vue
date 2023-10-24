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
		:tabindex="isSearched ? 0 : undefined"
		v-on="isSearched ? { click: handleClick, 'keydown.enter': handleClick } : {}"
		@keydown.enter="handleClick">
		<!-- Participant's avatar -->
		<AvatarWrapper :id="computedId"
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

		<!-- Call state icon -->
		<div v-if="callIcon"
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
			force-menu
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
			<NcActionButton v-if="canBeDemoted"
				close-after-click
				@click="demoteFromModerator">
				<template #icon>
					<Account :size="20" />
				</template>
				{{ t('spreed', 'Demote from moderator') }}
			</NcActionButton>
			<NcActionButton v-else-if="canBePromoted"
				close-after-click
				@click="promoteToModerator">
				<template #icon>
					<Crown :size="20" />
				</template>
				{{ t('spreed', 'Promote to moderator') }}
			</NcActionButton>
			<NcActionButton v-if="canBeModerated && isEmailActor"
				close-after-click
				@click="resendInvitation">
				<template #icon>
					<Email :size="20" />
				</template>
				{{ t('spreed', 'Resend invitation') }}
			</NcActionButton>
			<NcActionButton v-if="canSendCallNotification"
				close-after-click
				@click="sendCallNotification">
				<template #icon>
					<Bell :size="20" />
				</template>
				{{ t('spreed', 'Send call notification') }}
			</NcActionButton>
			<NcActionButton v-if="canBeModerated && isPhoneActor"
				close-after-click
				@click="copyPhoneNumber">
				<template #icon>
					<ContentCopy :size="20" />
				</template>
				{{ t('spreed', 'Copy phone number') }}
			</NcActionButton>

			<!-- Permissions -->
			<template v-if="showPermissionsOptions">
				<NcActionSeparator />
				<NcActionButton v-if="hasNonDefaultPermissions"
					close-after-click
					@click="applyDefaultPermissions">
					<template #icon>
						<LockReset :size="20" />
					</template>
					{{ t('spreed', 'Reset custom permissions') }}
				</NcActionButton>
				<NcActionButton close-after-click
					@click="grantAllPermissions">
					<template #icon>
						<LockOpenVariant :size="20" />
					</template>
					{{ t('spreed', 'Grant all permissions') }}
				</NcActionButton>
				<NcActionButton close-after-click
					@click="removeAllPermissions">
					<template #icon>
						<Lock :size="20" />
					</template>
					{{ t('spreed', 'Remove all permissions') }}
				</NcActionButton>
				<NcActionButton close-after-click
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
				close-after-click
				@click="removeParticipant">
				<template #icon>
					<Delete :size="20" />
				</template>
				{{ isGroup ? t('spreed', 'Remove group and members') : t('spreed', 'Remove participant') }}
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

import isEqual from 'lodash/isEqual.js'

import Account from 'vue-material-design-icons/Account.vue'
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
import Pencil from 'vue-material-design-icons/Pencil.vue'
import Phone from 'vue-material-design-icons/Phone.vue'
import Tune from 'vue-material-design-icons/Tune.vue'
import VideoIcon from 'vue-material-design-icons/Video.vue'

import { showError, showSuccess } from '@nextcloud/dialogs'

import NcActionButton from '@nextcloud/vue/dist/Components/NcActionButton.js'
import NcActions from '@nextcloud/vue/dist/Components/NcActions.js'
import NcActionSeparator from '@nextcloud/vue/dist/Components/NcActionSeparator.js'
import NcActionText from '@nextcloud/vue/dist/Components/NcActionText.js'
import Tooltip from '@nextcloud/vue/dist/Directives/Tooltip.js'

import AvatarWrapper from '../../../../AvatarWrapper/AvatarWrapper.vue'
import ParticipantPermissionsEditor from './ParticipantPermissionsEditor/ParticipantPermissionsEditor.vue'

import { useIsInCall } from '../../../../../composables/useIsInCall.js'
import { CONVERSATION, PARTICIPANT, ATTENDEE } from '../../../../../constants.js'
import { readableNumber } from '../../../../../utils/readableNumber.js'
import { formattedTime } from '../../../../../utils/formattedTime.js'
import { getStatusMessage } from '../../../../../utils/userStatus.js'

export default {
	name: 'Participant',

	components: {
		AvatarWrapper,
		NcActions,
		NcActionButton,
		NcActionText,
		NcActionSeparator,
		ParticipantPermissionsEditor,
		// Icons
		Account,
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
		Pencil,
		Phone,
		Tune,
		VideoIcon,
	},

	directives: {
		tooltip: Tooltip,
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

		/**
		 * Toggles the bulk selection state of this component
		 */
		isSelectable: {
			type: Boolean,
			default: false,
		},
	},

	emits: ['click-participant'],

	setup() {
		const isInCall = useIsInCall()
		return { isInCall }
	},

	data() {
		return {
			isUserNameTooltipVisible: false,
			isStatusTooltipVisible: false,
			permissionsEditor: false,
			speakingInterval: null,
			timeSpeaking: null,
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

		statusMessage() {
			if (this.isSpeakingStatusAvailable) {
				return this.isParticipantSpeaking
					? '💬 ' + t('spreed', '{time} talking …', { time: formattedTime(this.timeSpeaking, true) })
					: '💬 ' + t('spreed', '{time} talking time', { time: formattedTime(this.timeSpeaking, true) })
			} else {
				return getStatusMessage(this.participant)
			}
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
			if (this.isSelectable) {
				let isSelected = false
				this.$store.getters.selectedParticipants.forEach(selectedParticipant => {
					if (isEqual(selectedParticipant, this.participant)) {
						isSelected = true
					}
				})
				return isSelected
			}
			return false
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

		label() {
			return this.participant.label
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
			return this.$store.getters.getParticipantSpeakingInformation(this.token, this.attendeeId)
		},

		isParticipantSpeaking() {
			return this.participantSpeakingInformation?.speaking
		},

		lastPing() {
			return this.participant.lastPing
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
			return !this.sessionIds.length && !this.isSearched && (this.isUserActor || this.isGuestActor)
		},

		isGuest() {
			return [PARTICIPANT.TYPE.GUEST, PARTICIPANT.TYPE.GUEST_MODERATOR].includes(this.participantType)
		},

		isGroup() {
			return this.participant.actorType === ATTENDEE.ACTOR_TYPE.GROUPS
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
	},
	watch: {
		isParticipantSpeaking(speaking) {
			if (speaking) {
				if (!this.speakingInterval) {
					this.speakingInterval = setInterval(this.computeElapsedTime, 1000)
				}
			} else {
				if (speaking === undefined) {
					this.timeSpeaking = 0
				}
				clearInterval(this.speakingInterval)
				this.speakingInterval = null
			}
		},
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

		computeElapsedTime() {
			if (!this.participantSpeakingInformation) {
				return null
			}

			const { speaking, lastTimestamp, totalCountedTime } = this.participantSpeakingInformation

			if (!speaking) {
				this.timeSpeaking = totalCountedTime
			} else {
				this.timeSpeaking = Date.now() - lastTimestamp + totalCountedTime
			}
		},

		async copyPhoneNumber() {
			try {
				await navigator.clipboard.writeText(this.participant.phoneNumber)
				showSuccess(t('spreed', 'Phone number copied to clipboard'))
			} catch (error) {
				showError(t('spreed', 'Phone number could not be copied'))
			}
		}
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
