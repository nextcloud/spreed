<!--
  - SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<!-- Message Actions -->
	<div v-click-outside="handleClickOutside">
		<template v-if="!isReactionsMenuOpen">
			<NcButton v-if="canReact"
				type="tertiary"
				:aria-label="t('spreed', 'Add a reaction to this message')"
				:title="t('spreed', 'Add a reaction to this message')"
				@click="openReactionsMenu">
				<template #icon>
					<EmoticonOutline :size="20" />
				</template>
			</NcButton>
			<NcButton v-if="isReplyable && !isConversationReadOnly"
				type="tertiary"
				:aria-label="t('spreed', 'Reply')"
				:title="t('spreed', 'Reply')"
				@click="handleReply">
				<template #icon>
					<Reply :size="16" />
				</template>
			</NcButton>
			<NcActions :force-menu="true"
				placement="bottom-end"
				:container="messageContainer"
				:boundaries-element="boundariesElement"
				@open="onMenuOpen"
				@close="onMenuClose">
				<template v-if="submenu === null">
					<!-- Message timestamp -->
					<NcActionText>
						<template #icon>
							<span v-if="showCommonReadIcon"
								:title="commonReadIconTooltip"
								:aria-label="commonReadIconTooltip">
								<CheckAll :size="16" />
							</span>
							<span v-else-if="showSentIcon"
								:title="sentIconTooltip"
								:aria-label="sentIconTooltip">
								<Check :size="16" />
							</span>
							<ClockOutline v-else :size="16" />
						</template>
						{{ messageDateTime }}
					</NcActionText>
					<!-- Edited message timestamp -->
					<NcActionText v-if="lastEditTimestamp"
						class="edit-timestamp"
						:name="lastEditActorLabel">
						<template #icon>
							<ClockEditOutline :size="16" />
						</template>
						{{ editedDateTime }}
					</NcActionText>
					<NcActionSeparator />

					<NcActionButton v-if="supportReminders"
						class="action--nested"
						@click.stop="submenu = 'reminder'">
						<template #icon>
							<AlarmIcon :size="20" />
						</template>
						{{ t('spreed', 'Set reminder') }}
					</NcActionButton>
					<NcActionButton v-if="isPrivateReplyable"
						close-after-click
						@click.stop="handlePrivateReply">
						<template #icon>
							<AccountIcon :size="20" />
						</template>
						{{ t('spreed', 'Reply privately') }}
					</NcActionButton>
					<NcActionButton v-if="isEditable"
						:aria-label="t('spreed', 'Edit message')"
						close-after-click
						@click.stop="editMessage">
						<template #icon>
							<Pencil :size="20" />
						</template>
						{{ t('spreed', 'Edit message') }}
					</NcActionButton>
					<NcActionButton v-if="!isFileShareWithoutCaption"
						close-after-click
						@click.stop="handleCopyMessageText">
						<template #icon>
							<ContentCopy :size="20" />
						</template>
						{{ t('spreed', 'Copy formatted message') }}
					</NcActionButton>
					<NcActionButton close-after-click
						@click.stop="handleCopyMessageLink">
						<template #icon>
							<OpenInNewIcon :size="20" />
						</template>
						{{ t('spreed', 'Copy message link') }}
					</NcActionButton>
					<NcActionButton close-after-click
						@click.stop="handleMarkAsUnread">
						<template #icon>
							<EyeOffOutline :size="16" />
						</template>
						{{ t('spreed', 'Mark as unread') }}
					</NcActionButton>
					<NcActionLink v-if="linkToFile"
						:href="linkToFile">
						<template #icon>
							<File :size="20" />
						</template>
						{{ t('spreed', 'Go to file') }}
					</NcActionLink>
					<NcActionButton v-if="canForwardMessage && !isInNoteToSelf"
						close-after-click
						@click="forwardToNote">
						<template #icon>
							<Note :size="16" />
						</template>
						{{ t('spreed', 'Note to self') }}
					</NcActionButton>
					<NcActionButton v-if="canForwardMessage"
						close-after-click
						@click.stop="openForwarder">
						<template #icon>
							<Share :size="16" />
						</template>
						{{ t('spreed', 'Forward message') }}
					</NcActionButton>
					<NcActionSeparator v-if="messageActions.length > 0" />
					<NcActionButton v-for="action in messageActions"
						:key="action.label"
						:icon="action.icon"
						close-after-click
						@click="action.callback(messageApiData)">
						{{ action.label }}
					</NcActionButton>
					<NcActionButton v-if="isTranslationAvailable && !isFileShareWithoutCaption"
						close-after-click
						@click.stop="$emit('show-translate-dialog', true)"
						@close="$emit('show-translate-dialog', false)">
						<template #icon>
							<Translate :size="16" />
						</template>
						{{ t('spreed', 'Translate') }}
					</NcActionButton>
					<template v-if="isDeleteable">
						<NcActionSeparator />
						<NcActionButton close-after-click
							@click.stop="handleDelete">
							<template #icon>
								<DeleteIcon :size="16" />
							</template>
							{{ t('spreed', 'Delete') }}
						</NcActionButton>
					</template>
				</template>

				<template v-else-if="supportReminders && submenu === 'reminder'">
					<NcActionButton :aria-label="t('spreed', 'Back')"
						@click.stop="submenu = null">
						<template #icon>
							<ArrowLeft />
						</template>
						{{ t('spreed', 'Back') }}
					</NcActionButton>

					<NcActionButton v-if="currentReminder"
						close-after-click
						@click.stop="removeReminder">
						<template #icon>
							<CloseCircleOutline :size="20" />
						</template>
						{{ clearReminderLabel }}
					</NcActionButton>

					<NcActionSeparator />

					<NcActionButton v-for="option in reminderOptions"
						:key="option.key"
						:aria-label="option.ariaLabel"
						close-after-click
						@click.stop="setReminder(option.timestamp)">
						{{ option.label }}
					</NcActionButton>

					<!-- Custom DateTime picker for the reminder -->
					<NcActionSeparator />

					<NcActionInput type="datetime-local"
						is-native-picker
						:value="customReminderDateTime"
						:min="new Date()"
						@change="setCustomReminderDateTime">
						<template #icon>
							<CalendarClock :size="20" />
						</template>
					</NcActionInput>

					<NcActionButton :aria-label="t('spreed', 'Set custom reminder')"
						close-after-click
						@click.stop="setCustomReminder(customReminderDateTime)">
						<template #icon>
							<Check :size="20" />
						</template>
						{{ t('spreed', 'Set custom reminder') }}
					</NcActionButton>
				</template>
			</NcActions>
		</template>

		<template v-else>
			<NcButton type="tertiary"
				:aria-label="t('spreed', 'Close reactions menu')"
				@click="closeReactionsMenu">
				<template #icon>
					<ArrowLeft :size="20" />
				</template>
			</NcButton>
			<NcButton v-for="emoji in frequentlyUsedEmojis"
				:key="emoji"
				type="tertiary"
				:aria-label="t('spreed', 'React with {emoji}', { emoji })"
				@click="handleReactionClick(emoji)">
				<template #icon>
					<span>{{ emoji }}</span>
				</template>
			</NcButton>

			<NcEmojiPicker :container="mainContainer"
				:boundary="boundariesElement"
				placement="auto"
				@select="handleReactionClick"
				@after-show="onEmojiPickerOpen"
				@after-hide="onEmojiPickerClose">
				<NcButton type="tertiary"
					:aria-label="t('spreed', 'React with another emoji')">
					<template #icon>
						<Plus :size="20" />
					</template>
				</NcButton>
			</NcEmojiPicker>
		</template>
	</div>
</template>

<script>
import { vOnClickOutside as ClickOutside } from '@vueuse/components'
import { toRefs } from 'vue'

import AccountIcon from 'vue-material-design-icons/Account.vue'
import AlarmIcon from 'vue-material-design-icons/Alarm.vue'
import ArrowLeft from 'vue-material-design-icons/ArrowLeft.vue'
import CalendarClock from 'vue-material-design-icons/CalendarClock.vue'
import Check from 'vue-material-design-icons/Check.vue'
import CheckAll from 'vue-material-design-icons/CheckAll.vue'
import ClockEditOutline from 'vue-material-design-icons/ClockEditOutline.vue'
import ClockOutline from 'vue-material-design-icons/ClockOutline.vue'
import CloseCircleOutline from 'vue-material-design-icons/CloseCircleOutline.vue'
import ContentCopy from 'vue-material-design-icons/ContentCopy.vue'
import DeleteIcon from 'vue-material-design-icons/Delete.vue'
import EmoticonOutline from 'vue-material-design-icons/EmoticonOutline.vue'
import EyeOffOutline from 'vue-material-design-icons/EyeOffOutline.vue'
import File from 'vue-material-design-icons/File.vue'
import Note from 'vue-material-design-icons/NoteEditOutline.vue'
import OpenInNewIcon from 'vue-material-design-icons/OpenInNew.vue'
import Pencil from 'vue-material-design-icons/Pencil.vue'
import Plus from 'vue-material-design-icons/Plus.vue'
import Reply from 'vue-material-design-icons/Reply.vue'
import Share from 'vue-material-design-icons/Share.vue'
import Translate from 'vue-material-design-icons/Translate.vue'

import { getCapabilities } from '@nextcloud/capabilities'
// eslint-disable-next-line
// import { showError, showSuccess } from '@nextcloud/dialogs'
import moment from '@nextcloud/moment'

import NcActionButton from '@nextcloud/vue/dist/Components/NcActionButton.js'
import NcActionInput from '@nextcloud/vue/dist/Components/NcActionInput.js'
import NcActionLink from '@nextcloud/vue/dist/Components/NcActionLink.js'
import NcActions from '@nextcloud/vue/dist/Components/NcActions.js'
import NcActionSeparator from '@nextcloud/vue/dist/Components/NcActionSeparator.js'
import NcActionText from '@nextcloud/vue/dist/Components/NcActionText.js'
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcEmojiPicker from '@nextcloud/vue/dist/Components/NcEmojiPicker.js'
import { emojiSearch } from '@nextcloud/vue/dist/Functions/emoji.js'

import { useMessageInfo } from '../../../../../composables/useMessageInfo.js'
import { CONVERSATION, ATTENDEE } from '../../../../../constants.js'
import { getMessageReminder, removeMessageReminder, setMessageReminder } from '../../../../../services/remindersService.js'
import { useIntegrationsStore } from '../../../../../stores/integrations.js'
import { useReactionsStore } from '../../../../../stores/reactions.js'
import { copyConversationLinkToClipboard } from '../../../../../utils/handleUrl.ts'
import { parseMentions } from '../../../../../utils/textParse.ts'

const supportReminders = getCapabilities()?.spreed?.features?.includes('remind-me-later')

export default {
	name: 'MessageButtonsBar',

	components: {
		NcActionButton,
		NcActionInput,
		NcActionLink,
		NcActionSeparator,
		NcActionText,
		NcActions,
		NcButton,
		NcEmojiPicker,
		// Icons
		AccountIcon,
		AlarmIcon,
		ArrowLeft,
		CalendarClock,
		CloseCircleOutline,
		Check,
		CheckAll,
		ClockEditOutline,
		ClockOutline,
		ContentCopy,
		DeleteIcon,
		EmoticonOutline,
		EyeOffOutline,
		File,
		Note,
		OpenInNewIcon,
		Pencil,
		Plus,
		Reply,
		Share,
		Translate,
	},

	directives: {
		ClickOutside,
	},

	inject: ['getMessagesListScroller'],

	inheritAttrs: false,

	props: {
		token: {
			type: String,
			required: true,
		},

		previousMessageId: {
			type: [String, Number],
			required: true,
		},

		isReplyable: {
			type: Boolean,
			required: true,
		},

		actorId: {
			type: String,
			required: true,
		},

		actorType: {
			type: String,
			required: true,
		},

		/**
		 * The parameters of the rich object message
		 */
		messageParameters: {
			type: [Array, Object],
			required: true,
		},

		/**
		 * The message timestamp.
		 */
		timestamp: {
			type: Number,
			default: 0,
		},

		/**
		 * The message id.
		 */
		id: {
			type: [String, Number],
			required: true,
		},

		/**
		 * The message or quote text.
		 */
		message: {
			type: String,
			required: true,
		},

		/**
		 * The type of the message.
		 */
		messageType: {
			type: String,
			required: true,
		},

		lastEditActorDisplayName: {
			type: String,
			default: '',
		},
		lastEditTimestamp: {
			type: Number,
			default: 0,
		},

		isActionMenuOpen: {
			type: Boolean,
			required: true,
		},
		isEmojiPickerOpen: {
			type: Boolean,
			required: true,
		},
		isReactionsMenuOpen: {
			type: Boolean,
			required: true,
		},
		isForwarderOpen: {
			type: Boolean,
			required: true,
		},

		canReact: {
			type: Boolean,
			required: true,
		},
		/**
		 * Message read information
		 */
		showCommonReadIcon: {
			type: Boolean,
			required: true,
		},
		showSentIcon: {
			type: Boolean,
			required: true,
		},
		commonReadIconTooltip: {
			type: String,
			required: true,
		},
		sentIconTooltip: {
			type: String,
			required: true,
		},

		isTranslationAvailable: {
			type: Boolean,
			required: true,
		},
	},

	emits: ['delete', 'update:isActionMenuOpen', 'update:isEmojiPickerOpen', 'update:isReactionsMenuOpen', 'update:isForwarderOpen', 'show-translate-dialog', 'reply', 'edit'],

	setup(props) {
		const { token, id } = toRefs(props)
		const reactionsStore = useReactionsStore()
		const { messageActions } = useIntegrationsStore()
		const {
			isEditable,
			isDeleteable,
			isCurrentUserOwnMessage,
			isFileShare,
			isFileShareWithoutCaption,
			isConversationReadOnly,
			isConversationModifiable,
		} = useMessageInfo(token, id)

		return {
			messageActions,
			supportReminders,
			reactionsStore,
			isEditable,
			isCurrentUserOwnMessage,
			isFileShare,
			isFileShareWithoutCaption,
			isDeleteable,
			isConversationReadOnly,
			isConversationModifiable,
		}
	},

	data() {
		return {
			frequentlyUsedEmojis: [],
			submenu: null,
			currentReminder: null,
			customReminderDateTime: new Date(moment().add(2, 'hours').minute(0).second(0).valueOf()),
		}
	},

	computed: {
		conversation() {
			return this.$store.getters.conversation(this.token)
		},

		mainContainer() {
			return this.$store.getters.getMainContainerSelector()
		},

		messageContainer() {
			return `#message_${this.id}`
		},

		boundariesElement() {
			return this.getMessagesListScroller()
		},

		isPrivateReplyable() {
			return this.isReplyable
				&& (this.conversation.type === CONVERSATION.TYPE.PUBLIC
					|| this.conversation.type === CONVERSATION.TYPE.GROUP)
				&& !this.isCurrentUserOwnMessage
				&& this.actorType === ATTENDEE.ACTOR_TYPE.USERS
				&& this.$store.getters.isActorUser()
		},

		linkToFile() {
			if (this.isFileShare) {
				const firstFileKey = (Object.keys(this.messageParameters).find(key => key.startsWith('file')))
				return this.messageParameters?.[firstFileKey]?.link
			} else {
				return ''
			}
		},

		isCurrentGuest() {
			return this.$store.getters.isActorGuest()
		},

		isDeletedMessage() {
			return this.messageType === 'comment_deleted'
		},

		isPollMessage() {
			return this.messageType === 'comment'
				&& this.messageParameters?.object?.type === 'talk-poll'
		},

		isInNoteToSelf() {
			return this.conversation.type === CONVERSATION.TYPE.NOTE_TO_SELF
		},

		canForwardMessage() {
			return !this.isCurrentGuest
				&& !this.isFileShare
				&& !this.isDeletedMessage
				&& !this.isPollMessage
		},

		messageDateTime() {
			return moment(this.timestamp * 1000).format('lll')
		},

		editedDateTime() {
			return moment(this.lastEditTimestamp * 1000).format('lll')
		},

		reminderOptions() {
			const currentDateTime = moment()

			// Same day 18:00 PM (hidden if after 17:00 PM now)
			const laterTodayTime = (currentDateTime.hour() < 17)
				? moment().hour(18)
				: null

			// Tomorrow 08:00 AM
			const tomorrowTime = moment().add(1, 'days').hour(8)

			// Saturday 08:00 AM (hidden if Friday, Saturday or Sunday now)
			const thisWeekendTime = (currentDateTime.day() > 0 && currentDateTime.day() < 5)
				? moment().day(6).hour(8)
				: null

			// Next Monday 08:00 AM (hidden if Sunday now)
			const nextWeekTime = (currentDateTime.day() !== 0)
				? moment().add(1, 'weeks').day(1).hour(8)
				: null

			return [
				{
					key: 'laterToday',
					timestamp: this.getTimestamp(laterTodayTime),
					label: t('spreed', 'Later today – {timeLocale}', { timeLocale: laterTodayTime?.format('LT') }),
					ariaLabel: t('spreed', 'Set reminder for later today'),
				},
				{
					key: 'tomorrow',
					timestamp: this.getTimestamp(tomorrowTime),
					label: t('spreed', 'Tomorrow – {timeLocale}', { timeLocale: tomorrowTime?.format('ddd LT') }),
					ariaLabel: t('spreed', 'Set reminder for tomorrow'),
				},
				{
					key: 'thisWeekend',
					timestamp: this.getTimestamp(thisWeekendTime),
					label: t('spreed', 'This weekend – {timeLocale}', { timeLocale: thisWeekendTime?.format('ddd LT') }),
					ariaLabel: t('spreed', 'Set reminder for this weekend'),
				},
				{
					key: 'nextWeek',
					timestamp: this.getTimestamp(nextWeekTime),
					label: t('spreed', 'Next week – {timeLocale}', { timeLocale: nextWeekTime?.format('ddd LT') }),
					ariaLabel: t('spreed', 'Set reminder for next week'),
				},
			].filter(option => option.timestamp !== null)
		},

		clearReminderLabel() {
			if (!this.currentReminder) {
				return ''
			}
			return t('spreed', 'Clear reminder – {timeLocale}', { timeLocale: moment(this.currentReminder.timestamp * 1000).format('ddd LT') })
		},

		messageApiData() {
			return {
				message: this.$store.getters.message(this.token, this.id),
				metadata: this.$store.getters.conversation(this.token),
				apiVersion: 'v3',
			}
		},

		lastEditActorLabel() {
			return t('spreed', 'Edited by {actor}', {
				actor: this.lastEditActorDisplayName,
			})
		},
	},

	watch: {
		submenu(value) {
			if (value === 'reminder') {
				this.getReminder()
			}
		},
	},

	methods: {
		handleReply() {
			this.$emit('reply')
		},

		async handlePrivateReply() {
			// open the 1:1 conversation
			const conversation = await this.$store.dispatch('createOneToOneConversation', this.actorId)
			this.$router.push({ name: 'conversation', params: { token: conversation.token } }).catch(err => console.debug(`Error while pushing the new conversation's route: ${err}`))
		},

		async handleCopyMessageText() {
			const parsedText = parseMentions(this.message, this.messageParameters)

			try {
				await navigator.clipboard.writeText(parsedText)
				window.OCP.Toast.success(t('spreed', 'Message text copied to clipboard'))
			} catch (error) {
				window.OCP.Toast.error(t('spreed', 'Message text could not be copied'))
			}
		},

		handleCopyMessageLink() {
			copyConversationLinkToClipboard(this.token, this.id)
		},

		async handleMarkAsUnread() {
			// update in backend + visually
			await this.$store.dispatch('updateLastReadMessage', {
				token: this.token,
				id: this.previousMessageId,
				updateVisually: true,
			})
		},

		handleReactionClick(selectedEmoji) {
			// Add reaction only if user hasn't reacted yet
			if (!this.reactionsSelf?.includes(selectedEmoji)) {
				this.reactionsStore.addReactionToMessage({
					token: this.token,
					messageId: this.id,
					selectedEmoji,
				})
			} else {
				console.debug('user has already reacted, removing reaction')
				this.reactionsStore.removeReactionFromMessage({
					token: this.token,
					messageId: this.id,
					selectedEmoji,
				})
			}
			this.closeReactionsMenu()
		},

		handleDelete() {
			this.$emit('delete')
		},

		onMenuOpen() {
			this.$emit('update:isActionMenuOpen', true)
		},

		onMenuClose() {
			this.$emit('update:isActionMenuOpen', false)
		},

		onEmojiPickerOpen() {
			this.updateFrequentlyUsedEmojis()
			this.$emit('update:isEmojiPickerOpen', true)
		},

		onEmojiPickerClose() {
			this.$emit('update:isEmojiPickerOpen', false)
		},

		openReactionsMenu() {
			this.updateFrequentlyUsedEmojis()
			this.$emit('update:isReactionsMenuOpen', true)
		},

		async forwardToNote() {
			try {
				await this.$store.dispatch('forwardMessage', {
					messageToBeForwarded: this.$store.getters.message(this.token, this.id)
				})
				window.OCP.Toast.success(t('spreed', 'Message forwarded to "Note to self"'))
			} catch (error) {
				console.error('Error while forwarding message to "Note to self"', error)
				window.OCP.Toast.error(t('spreed', 'Error while forwarding message to "Note to self"'))
			}
		},

		openForwarder() {
			this.$emit('update:isForwarderOpen', true)
		},

		// Making sure that the click is outside the MessageButtonsBar
		handleClickOutside(event) {
			// check if click is inside the emoji picker
			if (event.composedPath().some(element => element.classList?.contains('v-popper__popper--shown'))) {
				return
			}

			if (event.composedPath().includes(this.$el)) {
				return
			}
			this.closeReactionsMenu()
		},

		closeReactionsMenu() {
			this.$emit('update:isReactionsMenuOpen', false)
		},

		updateFrequentlyUsedEmojis() {
			this.frequentlyUsedEmojis = emojiSearch('', 5).map(emoji => emoji.native)
		},

		getTimestamp(momentObject) {
			return momentObject?.minute(0).second(0).millisecond(0).valueOf() || null
		},

		async getReminder() {
			try {
				const response = await getMessageReminder(this.token, this.id)
				this.currentReminder = response.data.ocs.data
			} catch (error) {
				console.debug(error)
			}
		},

		async removeReminder() {
			try {
				await removeMessageReminder(this.token, this.id)
				window.OCP.Toast.success(t('spreed', 'A reminder was successfully removed'))
			} catch (error) {
				console.error(error)
				window.OCP.Toast.error(t('spreed', 'Error occurred when removing a reminder'))
			}
		},

		async setReminder(timestamp) {
			try {
				await setMessageReminder(this.token, this.id, timestamp / 1000)
				window.OCP.Toast.success(t('spreed', 'A reminder was successfully set at {datetime}', {
					datetime: moment(timestamp).format('LLL'),
				}))
			} catch (error) {
				console.error(error)
				window.OCP.Toast.error(t('spreed', 'Error occurred when creating a reminder'))
			}
		},

		setCustomReminderDateTime(event) {
			this.customReminderDateTime = new Date(event.target.value)
		},

		setCustomReminder() {
			this.setReminder(this.customReminderDateTime.valueOf())
		},

		editMessage() {
			if (!this.isEditable) {
				return
			}
			this.$emit('edit')
		},
	},
}
</script>

<style lang="scss" scoped>
.action--nested {
	:deep(.action-button::after) {
		content: " ";
		width: 20px;
		height: 44px;
		margin-left: auto;
		background: no-repeat center var(--icon-triangle-e-dark);
	}
}

.edit-timestamp :deep(.action-text__longtext-wrapper) {
	padding: 0;
}
</style>
