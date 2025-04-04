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
			<NcButton v-if="canReply"
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
				:boundaries-element="boundariesElement"
				@open="onMenuOpen"
				@close="onMenuClose">
				<template v-if="submenu === null">
					<!-- Message timestamp -->
					<NcActionText>
						<template #icon>
							<span v-if="readInfo.showCommonReadIcon"
								:title="readInfo.commonReadIconTitle"
								:aria-label="readInfo.commonReadIconTitle">
								<CheckAll :size="16" />
							</span>
							<span v-else-if="readInfo.showSentIcon"
								:title="readInfo.sentIconTitle"
								:aria-label="readInfo.sentIconTitle">
								<Check :size="16" />
							</span>
							<ClockOutline v-else :size="16" />
						</template>
						{{ messageDateTime }}
					</NcActionText>
					<!-- Edited message timestamp -->
					<NcActionText v-if="message.lastEditTimestamp"
						class="edit-timestamp"
						:name="lastEditActorLabel">
						<template #icon>
							<ClockEditOutline :size="16" />
						</template>
						{{ editedDateTime }}
					</NcActionText>
					<!-- Silent message information -->
					<NcActionText v-if="readInfo.showSilentIcon">
						<template #icon>
							<IconBellOff :size="16" />
						</template>
						{{ readInfo.silentIconTitle }}
					</NcActionText>
					<NcActionSeparator />

					<NcActionButton v-if="supportReminders"
						is-menu
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
						{{ t('spreed', 'Copy message') }}
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
					<template v-if="isFileShare">
						<NcActionSeparator />
						<NcActionLink :href="messageFile.link">
							<template #icon>
								<File :size="20" />
							</template>
							{{ t('spreed', 'Go to file') }}
						</NcActionLink>
						<NcActionLink :href="linkToFileDownload" :download="messageFile.name">
							<template #icon>
								<IconDownload :size="20" />
							</template>
							{{ t('spreed', 'Download file') }}
						</NcActionLink>
					</template>
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
						@click="handleMessageAction(action)">
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
							<IconArrowLeft class="bidirectional-icon" />
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

					<NcActionInput v-model="customReminderDateTime"
						type="datetime-local"
						is-native-picker
						:min="new Date()">
						<template #icon>
							<CalendarClock :size="20" />
						</template>
					</NcActionInput>

					<NcActionButton :aria-label="t('spreed', 'Set custom reminder')"
						close-after-click
						@click.stop="setReminder(customReminderTimestamp)">
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
					<IconArrowLeft class="bidirectional-icon" :size="20" />
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

			<NcEmojiPicker :boundary="boundariesElement"
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
import IconArrowLeft from 'vue-material-design-icons/ArrowLeft.vue'
import IconBellOff from 'vue-material-design-icons/BellOff.vue'
import CalendarClock from 'vue-material-design-icons/CalendarClock.vue'
import Check from 'vue-material-design-icons/Check.vue'
import CheckAll from 'vue-material-design-icons/CheckAll.vue'
import ClockEditOutline from 'vue-material-design-icons/ClockEditOutline.vue'
import ClockOutline from 'vue-material-design-icons/ClockOutline.vue'
import CloseCircleOutline from 'vue-material-design-icons/CloseCircleOutline.vue'
import ContentCopy from 'vue-material-design-icons/ContentCopy.vue'
import DeleteIcon from 'vue-material-design-icons/Delete.vue'
import IconDownload from 'vue-material-design-icons/Download.vue'
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

import { getCurrentUser } from '@nextcloud/auth'
import { showError, showSuccess } from '@nextcloud/dialogs'
import { t } from '@nextcloud/l10n'

import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcActionInput from '@nextcloud/vue/components/NcActionInput'
import NcActionLink from '@nextcloud/vue/components/NcActionLink'
import NcActions from '@nextcloud/vue/components/NcActions'
import NcActionSeparator from '@nextcloud/vue/components/NcActionSeparator'
import NcActionText from '@nextcloud/vue/components/NcActionText'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcEmojiPicker from '@nextcloud/vue/components/NcEmojiPicker'
import { emojiSearch } from '@nextcloud/vue/functions/emoji'

import { useMessageInfo } from '../../../../../composables/useMessageInfo.js'
import { CONVERSATION, ATTENDEE, PARTICIPANT } from '../../../../../constants.ts'
import { hasTalkFeature } from '../../../../../services/CapabilitiesManager.ts'
import { getMessageReminder, removeMessageReminder, setMessageReminder } from '../../../../../services/remindersService.js'
import { useIntegrationsStore } from '../../../../../stores/integrations.js'
import { useReactionsStore } from '../../../../../stores/reactions.js'
import { generatePublicShareDownloadUrl, generateUserFileUrl } from '../../../../../utils/davUtils.ts'
import { convertToUnix, formatDateTime } from '../../../../../utils/formattedTime.ts'
import { copyConversationLinkToClipboard } from '../../../../../utils/handleUrl.ts'
import { parseMentions } from '../../../../../utils/textParse.ts'

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
		IconArrowLeft,
		IconBellOff,
		CalendarClock,
		CloseCircleOutline,
		Check,
		CheckAll,
		ClockEditOutline,
		ClockOutline,
		ContentCopy,
		IconDownload,
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

	props: {
		previousMessageId: {
			type: [String, Number],
			required: true,
		},

		message: {
			type: Object,
			required: true,
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

		readInfo: {
			type: Object,
			required: true,
		},

		isTranslationAvailable: {
			type: Boolean,
			required: true,
		},
	},

	emits: ['delete', 'update:isActionMenuOpen', 'update:isEmojiPickerOpen', 'update:isReactionsMenuOpen', 'update:isForwarderOpen', 'show-translate-dialog', 'reply', 'edit'],

	setup(props) {
		const { message } = toRefs(props)
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
		} = useMessageInfo(message)
		const supportReminders = hasTalkFeature(message.value.token, 'remind-me-later')

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
			customReminderTimestamp: new Date().setHours(new Date().getHours() + 2, 0, 0, 0),
		}
	},

	computed: {
		conversation() {
			return this.$store.getters.conversation(this.message.token)
		},

		boundariesElement() {
			return this.getMessagesListScroller()
		},

		isPrivateReplyable() {
			return this.message.isReplyable
				&& (this.conversation.type === CONVERSATION.TYPE.PUBLIC
					|| this.conversation.type === CONVERSATION.TYPE.GROUP)
				&& !this.isCurrentUserOwnMessage
				&& this.message.actorType === ATTENDEE.ACTOR_TYPE.USERS
				&& this.$store.getters.isActorUser()
		},

		messageFile() {
			const firstFileKey = (Object.keys(this.message.messageParameters).find(key => key.startsWith('file')))
			return this.message.messageParameters[firstFileKey]
		},

		linkToFileDownload() {
			return getCurrentUser()
				? generateUserFileUrl(this.messageFile.path)
				: generatePublicShareDownloadUrl(this.messageFile.link)
		},

		isCurrentGuest() {
			return this.$store.getters.isActorGuest()
		},

		isDeletedMessage() {
			return this.message.messageType === 'comment_deleted'
		},

		isPollMessage() {
			return this.message.messageType === 'comment'
				&& this.message.messageParameters?.object?.type === 'talk-poll'
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
			return formatDateTime(this.message.timestamp * 1000, 'lll')
		},

		editedDateTime() {
			return formatDateTime(this.message.lastEditTimestamp * 1000, 'lll')
		},

		customReminderDateTime: {
			get() {
				return new Date(this.customReminderTimestamp)
			},
			set(value) {
				if (value !== null) {
					this.customReminderTimestamp = value.valueOf()
				}
			},
		},

		reminderOptions() {
			const currentDate = new Date()
			const currentDayOfWeek = currentDate.getDay()

			const nextDay = new Date()
			nextDay.setDate(currentDate.getDate() + 1)

			const nextSaturday = new Date()
			nextSaturday.setDate(currentDate.getDate() + ((6 + 7 - currentDayOfWeek) % 7 || 7))

			const nextMonday = new Date()
			nextMonday.setDate(currentDate.getDate() + ((1 + 7 - currentDayOfWeek) % 7 || 7))

			// Same day 18:00 PM (hidden if after 17:00 PM now)
			const laterTodayTime = (currentDate.getHours() < 17)
				? new Date().setHours(18, 0, 0, 0)
				: null

			// Tomorrow 08:00 AM
			const tomorrowTime = nextDay.setHours(8, 0, 0, 0)

			// Saturday 08:00 AM (hidden if Friday, Saturday or Sunday now)
			const thisWeekendTime = (![0, 5, 6].includes(currentDayOfWeek))
				? nextSaturday.setHours(8, 0, 0, 0)
				: null

			// Next Monday 08:00 AM (hidden if Sunday now)
			// TODO: use getFirstDay from nextcloud/l10n
			const nextWeekTime = (currentDayOfWeek !== 0)
				? nextMonday.setHours(8, 0, 0, 0)
				: null

			return [
				{
					key: 'laterToday',
					timestamp: laterTodayTime,
					label: t('spreed', 'Later today – {timeLocale}', { timeLocale: formatDateTime(laterTodayTime, 'LT') }),
					ariaLabel: t('spreed', 'Set reminder for later today'),
				},
				{
					key: 'tomorrow',
					timestamp: tomorrowTime,
					label: t('spreed', 'Tomorrow – {timeLocale}', { timeLocale: formatDateTime(tomorrowTime, 'ddd LT') }),
					ariaLabel: t('spreed', 'Set reminder for tomorrow'),
				},
				{
					key: 'thisWeekend',
					timestamp: thisWeekendTime,
					label: t('spreed', 'This weekend – {timeLocale}', { timeLocale: formatDateTime(thisWeekendTime, 'ddd LT') }),
					ariaLabel: t('spreed', 'Set reminder for this weekend'),
				},
				{
					key: 'nextWeek',
					timestamp: nextWeekTime,
					label: t('spreed', 'Next week – {timeLocale}', { timeLocale: formatDateTime(nextWeekTime, 'ddd LT') }),
					ariaLabel: t('spreed', 'Set reminder for next week'),
				},
			].filter(option => option.timestamp !== null)
		},

		clearReminderLabel() {
			if (!this.currentReminder) {
				return ''
			}
			return t('spreed', 'Clear reminder – {timeLocale}', { timeLocale: formatDateTime(this.currentReminder.timestamp * 1000, 'ddd LT') })
		},

		lastEditActorLabel() {
			return t('spreed', 'Edited by {actor}', {
				actor: this.message.lastEditActorDisplayName,
			})
		},

		canReply() {
			return this.message.isReplyable && !this.isConversationReadOnly && (this.conversation.permissions & PARTICIPANT.PERMISSIONS.CHAT) !== 0
		}
	},

	watch: {
		submenu(value) {
			if (value === 'reminder') {
				this.getReminder()
			}
		},
	},

	methods: {
		t,
		handleReply() {
			this.$emit('reply')
		},

		async handlePrivateReply() {
			// open the 1:1 conversation
			const conversation = await this.$store.dispatch('createOneToOneConversation', this.message.actorId)
			this.$router.push({ name: 'conversation', params: { token: conversation.token } }).catch(err => console.debug(`Error while pushing the new conversation's route: ${err}`))
		},

		async handleCopyMessageText() {
			const parsedText = parseMentions(this.message.message, this.message.messageParameters)

			try {
				await navigator.clipboard.writeText(parsedText)
				showSuccess(t('spreed', 'Message text copied to clipboard'))
			} catch (error) {
				showError(t('spreed', 'Message text could not be copied'))
			}
		},

		handleCopyMessageLink() {
			copyConversationLinkToClipboard(this.message.token, this.message.id)
		},

		async handleMarkAsUnread() {
			// update in backend + visually
			await this.$store.dispatch('updateLastReadMessage', {
				token: this.message.token,
				id: this.previousMessageId,
				updateVisually: true,
			})
		},

		handleReactionClick(selectedEmoji) {
			// Add reaction only if user hasn't reacted yet
			if (!this.message.reactionsSelf?.includes(selectedEmoji)) {
				this.reactionsStore.addReactionToMessage({
					token: this.message.token,
					messageId: this.message.id,
					selectedEmoji,
				})
			} else {
				console.debug('user has already reacted, removing reaction')
				this.reactionsStore.removeReactionFromMessage({
					token: this.message.token,
					messageId: this.message.id,
					selectedEmoji,
				})
			}
			this.closeReactionsMenu()
		},

		handleMessageAction(action) {
			action.callback({ message: this.message, metadata: this.conversation, apiVersion: 'v3' })
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
					messageToBeForwarded: this.$store.getters.message(this.message.token, this.message.id)
				})
				showSuccess(t('spreed', 'Message forwarded to "Note to self"'))
			} catch (error) {
				console.error('Error while forwarding message to "Note to self"', error)
				showError(t('spreed', 'Error while forwarding message to "Note to self"'))
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

		async getReminder() {
			try {
				const response = await getMessageReminder(this.message.token, this.message.id)
				this.currentReminder = response.data.ocs.data
			} catch (error) {
				console.debug(error)
			}
		},

		async removeReminder() {
			try {
				await removeMessageReminder(this.message.token, this.message.id)
				showSuccess(t('spreed', 'A reminder was successfully removed'))
			} catch (error) {
				console.error(error)
				showError(t('spreed', 'Error occurred when removing a reminder'))
			}
		},

		async setReminder(timestamp) {
			try {
				await setMessageReminder(this.message.token, this.message.id, convertToUnix(timestamp))
				showSuccess(t('spreed', 'A reminder was successfully set at {datetime}', {
					datetime: formatDateTime(timestamp, 'LLL'),
				}))
			} catch (error) {
				console.error(error)
				showError(t('spreed', 'Error occurred when creating a reminder'))
			}
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
.edit-timestamp :deep(.action-text__longtext-wrapper) {
	padding: 0;
}
</style>
