<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\AuditLog;

/**
 * Operations Talk writes to the admin audit log, in the form `app.entity.action`
 *
 * The values are matched by log consumers (filters, alert rules, SIEM imports),
 * so changing one is a breaking change for them. Case names may be renamed.
 */
enum Operation: string {
	// Conversations
	case CONVERSATION_CREATED = 'spreed.conversation.created';
	case CONVERSATION_DELETED = 'spreed.conversation.deleted';
	case CONVERSATION_TYPE_CHANGED = 'spreed.conversation_type.changed';
	case CONVERSATION_PASSWORD_CHANGED = 'spreed.conversation_password.changed';
	case CONVERSATION_LISTABLE_CHANGED = 'spreed.conversation_listable.changed';
	case CONVERSATION_LOBBY_CHANGED = 'spreed.conversation_lobby.changed';
	case CONVERSATION_READ_ONLY_CHANGED = 'spreed.conversation_read_only.changed';
	case CONVERSATION_SIP_CHANGED = 'spreed.conversation_sip.changed';
	case CONVERSATION_PERMISSIONS_CHANGED = 'spreed.conversation_permissions.changed';
	case CONVERSATION_MENTION_PERMISSIONS_CHANGED = 'spreed.conversation_mention_permissions.changed';
	case CONVERSATION_MESSAGE_EXPIRATION_CHANGED = 'spreed.conversation_message_expiration.changed';
	case CONVERSATION_RECORDING_CONSENT_CHANGED = 'spreed.conversation_recording_consent.changed';

	// Participants
	case PARTICIPANT_ADDED = 'spreed.participant.added';
	case PARTICIPANT_REMOVED = 'spreed.participant.removed';
	case PARTICIPANT_TYPE_CHANGED = 'spreed.participant_type.changed';
	case PARTICIPANT_PERMISSIONS_CHANGED = 'spreed.participant_permissions.changed';
	case BAN_CREATED = 'spreed.ban.created';
	case BAN_DELETED = 'spreed.ban.deleted';
	case EXTERNAL_CALL_POLICY_PARTICIPANT_REMOVED = 'spreed.external_call_policy.participant_removed';

	// Federation
	case FEDERATION_INVITE_ACCEPTED = 'spreed.federation_invite.accepted';
	case FEDERATION_INVITE_REJECTED = 'spreed.federation_invite.rejected';

	// Recording
	case RECORDING_STARTED = 'spreed.recording.started';
	case RECORDING_STOPPED = 'spreed.recording.stopped';
	case RECORDING_STORED = 'spreed.recording.stored';

	// Chat
	case MESSAGE_DELETED = 'spreed.message.deleted';
	case MESSAGE_EDITED = 'spreed.message.edited';
	case CHAT_HISTORY_CLEARED = 'spreed.chat_history.cleared';

	// Bots
	case CONVERSATION_BOT_ENABLED = 'spreed.conversation_bot.enabled';
	case CONVERSATION_BOT_DISABLED = 'spreed.conversation_bot.disabled';
	case BOT_INSTALLED = 'spreed.bot.installed';
	case BOT_UNINSTALLED = 'spreed.bot.uninstalled';

	// Administration
	case SIP_SETTINGS_UPDATED = 'spreed.sip_settings.updated';
	case HOSTED_SIGNALING_ACCOUNT_CREATED = 'spreed.hosted_signaling_account.created';
	case HOSTED_SIGNALING_ACCOUNT_DELETED = 'spreed.hosted_signaling_account.deleted';
	case BRIDGE_UPDATED = 'spreed.bridge.updated';
	case BRIDGE_DELETED = 'spreed.bridge.deleted';
	case CALL_PARTICIPANTS_EXPORTED = 'spreed.call_participants.exported';

	/**
	 * Short description of when the operation is logged, used for the documentation
	 */
	public function description(): string {
		return match ($this) {
			self::CONVERSATION_CREATED => 'A group or public conversation was created',
			self::CONVERSATION_DELETED => 'A conversation was deleted',
			self::CONVERSATION_TYPE_CHANGED => 'The type of a conversation was changed, for example from group to public',
			self::CONVERSATION_PASSWORD_CHANGED => 'Password protection of a conversation was turned on or off',
			self::CONVERSATION_LISTABLE_CHANGED => 'Who can find and join a conversation without an invitation was changed',
			self::CONVERSATION_LOBBY_CHANGED => 'The lobby of a conversation was turned on or off, or its timer was changed',
			self::CONVERSATION_READ_ONLY_CHANGED => 'A conversation was made read-only or writable again',
			self::CONVERSATION_SIP_CHANGED => 'SIP dial-in of a conversation was changed',
			self::CONVERSATION_PERMISSIONS_CHANGED => 'The default permissions of a conversation were changed',
			self::CONVERSATION_MENTION_PERMISSIONS_CHANGED => 'Who may mention everyone in a conversation was changed',
			self::CONVERSATION_MESSAGE_EXPIRATION_CHANGED => 'The message expiration of a conversation was changed',
			self::CONVERSATION_RECORDING_CONSENT_CHANGED => 'The recording consent requirement of a conversation was changed',
			self::PARTICIPANT_ADDED => 'A participant was added to a group or public conversation',
			self::PARTICIPANT_REMOVED => 'A participant was removed from or left a group or public conversation',
			self::PARTICIPANT_TYPE_CHANGED => 'The participant type of a participant was changed, for example to moderator',
			self::PARTICIPANT_PERMISSIONS_CHANGED => 'The permissions of one participant or of all participants were changed',
			self::BAN_CREATED => 'A participant or IP address was banned from a conversation',
			self::BAN_DELETED => 'A ban was removed from a conversation',
			self::EXTERNAL_CALL_POLICY_PARTICIPANT_REMOVED => 'A participant was removed because the external call service did not allow them',
			self::FEDERATION_INVITE_ACCEPTED => 'An invitation to a conversation on another server was accepted',
			self::FEDERATION_INVITE_REJECTED => 'An invitation to a conversation on another server was rejected',
			self::RECORDING_STARTED => 'A call recording was started',
			self::RECORDING_STOPPED => 'A call recording was stopped',
			self::RECORDING_STORED => 'A call recording was stored in the folder of its owner',
			self::MESSAGE_DELETED => 'A moderator deleted a message of another participant',
			self::MESSAGE_EDITED => 'A moderator edited a message of another participant',
			self::CHAT_HISTORY_CLEARED => 'The chat history of a conversation was cleared',
			self::CONVERSATION_BOT_ENABLED => 'A bot was enabled in a conversation',
			self::CONVERSATION_BOT_DISABLED => 'A bot was disabled in a conversation',
			self::BOT_INSTALLED => 'A bot was installed through the bot install event',
			self::BOT_UNINSTALLED => 'A bot was uninstalled through the bot uninstall event',
			self::SIP_SETTINGS_UPDATED => 'The SIP bridge settings were changed',
			self::HOSTED_SIGNALING_ACCOUNT_CREATED => 'A hosted signaling server account was registered',
			self::HOSTED_SIGNALING_ACCOUNT_DELETED => 'The hosted signaling server account was deleted',
			self::BRIDGE_UPDATED => 'The Matterbridge of a conversation was updated',
			self::BRIDGE_DELETED => 'The Matterbridge of a conversation was deleted',
			self::CALL_PARTICIPANTS_EXPORTED => 'The participants list of a call was downloaded',
		};
	}
}
