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
		};
	}
}
