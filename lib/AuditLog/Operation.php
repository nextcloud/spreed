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
	case ConversationCreated = 'spreed.conversation.created';
	case ConversationDeleted = 'spreed.conversation.deleted';
	case ConversationTypeChanged = 'spreed.conversation_type.changed';
	case ConversationPasswordChanged = 'spreed.conversation_password.changed';
	case ConversationListableChanged = 'spreed.conversation_listable.changed';
	case ConversationLobbyChanged = 'spreed.conversation_lobby.changed';
	case ConversationReadOnlyChanged = 'spreed.conversation_read_only.changed';
	case ConversationSipChanged = 'spreed.conversation_sip.changed';
	case ConversationPermissionsChanged = 'spreed.conversation_permissions.changed';
	case ConversationMentionPermissionsChanged = 'spreed.conversation_mention_permissions.changed';
	case ConversationMessageExpirationChanged = 'spreed.conversation_message_expiration.changed';
	case ConversationRecordingConsentChanged = 'spreed.conversation_recording_consent.changed';

	// Participants
	case ParticipantAdded = 'spreed.participant.added';
	case ParticipantRemoved = 'spreed.participant.removed';
	case ParticipantTypeChanged = 'spreed.participant_type.changed';
	case ParticipantPermissionsChanged = 'spreed.participant_permissions.changed';
	case BanCreated = 'spreed.ban.created';
	case BanDeleted = 'spreed.ban.deleted';
	case ExternalCallPolicyParticipantRemoved = 'spreed.external_call_policy.participant_removed';

	// Federation
	case FederationInviteAccepted = 'spreed.federation_invite.accepted';
	case FederationInviteRejected = 'spreed.federation_invite.rejected';

	// Recording
	case RecordingStarted = 'spreed.recording.started';
	case RecordingStopped = 'spreed.recording.stopped';
	case RecordingStored = 'spreed.recording.stored';

	// Chat
	case MessageDeleted = 'spreed.message.deleted';
	case MessageEdited = 'spreed.message.edited';
	case ChatHistoryCleared = 'spreed.chat_history.cleared';

	// Bots
	case ConversationBotEnabled = 'spreed.conversation_bot.enabled';
	case ConversationBotDisabled = 'spreed.conversation_bot.disabled';
	case BotInstalled = 'spreed.bot.installed';
	case BotUninstalled = 'spreed.bot.uninstalled';

	// Administration
	case SipSettingsUpdated = 'spreed.sip_settings.updated';
	case HostedSignalingAccountCreated = 'spreed.hosted_signaling_account.created';
	case HostedSignalingAccountDeleted = 'spreed.hosted_signaling_account.deleted';
	case BridgeUpdated = 'spreed.bridge.updated';
	case BridgeDeleted = 'spreed.bridge.deleted';
	case CallParticipantsExported = 'spreed.call_participants.exported';

	/**
	 * Short description of when the operation is logged, used for the documentation
	 */
	public function description(): string {
		return match ($this) {
			self::ConversationCreated => 'A group or public conversation was created',
			self::ConversationDeleted => 'A conversation was deleted',
			self::ConversationTypeChanged => 'The type of a conversation was changed, for example from group to public',
			self::ConversationPasswordChanged => 'Password protection of a conversation was turned on or off',
			self::ConversationListableChanged => 'Who can find and join a conversation without an invitation was changed',
			self::ConversationLobbyChanged => 'The lobby of a conversation was turned on or off, or its timer was changed',
			self::ConversationReadOnlyChanged => 'A conversation was made read-only or writable again',
			self::ConversationSipChanged => 'SIP dial-in of a conversation was changed',
			self::ConversationPermissionsChanged => 'The default permissions of a conversation were changed',
			self::ConversationMentionPermissionsChanged => 'Who may mention everyone in a conversation was changed',
			self::ConversationMessageExpirationChanged => 'The message expiration of a conversation was changed',
			self::ConversationRecordingConsentChanged => 'The recording consent requirement of a conversation was changed',
			self::ParticipantAdded => 'A participant was added to a group or public conversation',
			self::ParticipantRemoved => 'A participant was removed from or left a group or public conversation',
			self::ParticipantTypeChanged => 'The participant type of a participant was changed, for example to moderator',
			self::ParticipantPermissionsChanged => 'The permissions of one participant or of all participants were changed',
			self::BanCreated => 'A participant or IP address was banned from a conversation',
			self::BanDeleted => 'A ban was removed from a conversation',
			self::ExternalCallPolicyParticipantRemoved => 'A participant was removed because the external call service did not allow them',
			self::FederationInviteAccepted => 'An invitation to a conversation on another server was accepted',
			self::FederationInviteRejected => 'An invitation to a conversation on another server was rejected',
			self::RecordingStarted => 'A call recording was started',
			self::RecordingStopped => 'A call recording was stopped',
			self::RecordingStored => 'A call recording was stored in the folder of its owner',
			self::MessageDeleted => 'A moderator deleted a message of another participant',
			self::MessageEdited => 'A moderator edited a message of another participant',
			self::ChatHistoryCleared => 'The chat history of a conversation was cleared',
			self::ConversationBotEnabled => 'A bot was enabled in a conversation',
			self::ConversationBotDisabled => 'A bot was disabled in a conversation',
			self::BotInstalled => 'A bot was installed through the bot install event',
			self::BotUninstalled => 'A bot was uninstalled through the bot uninstall event',
			self::SipSettingsUpdated => 'The SIP bridge settings were changed',
			self::HostedSignalingAccountCreated => 'A hosted signaling server account was registered',
			self::HostedSignalingAccountDeleted => 'The hosted signaling server account was deleted',
			self::BridgeUpdated => 'The Matterbridge of a conversation was updated',
			self::BridgeDeleted => 'The Matterbridge of a conversation was deleted',
			self::CallParticipantsExported => 'The participants list of a call was downloaded',
		};
	}
}
