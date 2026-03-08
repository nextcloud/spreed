<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk;

/**
 * @psalm-type TalkActorTypes = 'users'|'groups'|'guests'|'emails'|'circles'|'bridged'|'bots'|'federated_users'|'phones'
 *
 * @psalm-type TalkBan = array{
 *     // Identifier of the ban
 *     id: int,
 *     // Actor type of the moderator that banned the participant
 *     moderatorActorType: string,
 *     // Actor id of the moderator that banned the participant
 *     moderatorActorId: string,
 *     // Display name of the moderator that banned the participant
 *     moderatorDisplayName: string,
 *     // Actor type of the banned participant
 *     bannedActorType: string,
 *     // Actor id of the banned participant
 *     bannedActorId: string,
 *     // Display name of the banned participant
 *     bannedDisplayName: string,
 *     // UNIX timestamp when the participant was banned
 *     bannedTime: int,
 *     // Internal note for the moderator to remember the reason for the ban
 *     internalNote: string,
 * }
 *
 * @psalm-type TalkBot = array{
 *     // A longer description of the bot helping moderators to decide if they want to enable this bot
 *     description: ?string,
 *     // Unique numeric identifier of the bot on this server
 *     id: int,
 *     // Display name of the bot shown as author when it posts a message or reaction
 *     name: string,
 *     // One of the [Bot states](https://nextcloud-talk.readthedocs.io/en/latest/constants#bot-states)
 *     state: int,
 * }
 *
 * @psalm-type TalkBotWithDetails = TalkBot&array{
 *     // Number of consecutive errors
 *     error_count: int,
 *     // Feature flags for the bot (see [constants list](https://nextcloud-talk.readthedocs.io/en/latest/constants#bot-features))
 *     features: int,
 *     // UNIX timestamp of the last error
 *     last_error_date: int,
 *     // The last exception message or error response information when trying to reach the bot
 *     last_error_message: string,
 *     // URL endpoint that is triggered by this bot
 *     url: string,
 *     // Hash of the URL prefixed with `bot-` serves as `actorId`
 *     url_hash: string,
 * }
 *
 * @psalm-type TalkCallPeer = array{
 *     // The user id, guest random id or email address of the attendee
 *     actorId: string,
 *     // Actor type of the attendee (see [Constants - Attendee types](https://nextcloud-talk.readthedocs.io/en/latest/constants#attendee-types))
 *     actorType: string,
 *     // The display name of the attendee
 *     displayName: string,
 *     // Timestamp of the last ping of the user (should be used for sorting)
 *     lastPing: int,
 *     // up to 512 character long string
 *     sessionId: string,
 *     // Conversation token
 *     token: string,
 * }
 *
 * @psalm-type TalkChatMentionSuggestion = array{
 *     // The actor id of the suggestion
 *     id: string,
 *     // The display name of the suggestion
 *     label: string,
 *     // The source of the suggestion (e.g. `users`, `groups`, `calls`)
 *     source: string,
 *     // The mention id to use for the mention
 *     mentionId: string,
 *     // Additional details of the suggestion (e.g. group description)
 *     details?: string,
 *     // User status of the suggestion
 *     status?: string,
 *     // UNIX timestamp when the user status will be cleared
 *     statusClearAt?: ?int,
 *     // User status icon
 *     statusIcon?: ?string,
 *     // User status message
 *     statusMessage?: ?string,
 * }
 *
 * @psalm-type TalkRichObjectParameter = array{
 *     // Object type (see [Rich Object String](https://github.com/nextcloud/server/issues/1706))
 *     type: string,
 *     // Object id
 *     id: string,
 *     // Visible name
 *     name: string,
 *     // Server URL for federated users
 *     server?: string,
 *     // URL of the object
 *     link?: string,
 *     // Type of the call
 *     'call-type'?: 'one2one'|'group'|'public',
 *     // URL of the icon
 *     'icon-url'?: string,
 *     // ID of a message that this object refers to
 *     'message-id'?: string,
 *     // Name of the Deck board
 *     boardname?: string,
 *     // Name of the Deck stack
 *     stackname?: string,
 *     // File size in bytes
 *     size?: string,
 *     // Path of the file
 *     path?: string,
 *     // Mimetype of the file
 *     mimetype?: string,
 *     // Whether a preview is available for the file
 *     'preview-available'?: 'yes'|'no',
 *     // Whether the download should be hidden for the file
 *     'hide-download'?: 'yes'|'no',
 *     // Modification time of the file as UNIX timestamp
 *     mtime?: string,
 *     // Latitude of a location
 *     latitude?: string,
 *     // Longitude of a location
 *     longitude?: string,
 *     // Description of the object
 *     description?: string,
 *     // URL of a thumbnail
 *     thumb?: string,
 *     // Website URL
 *     website?: string,
 *     // Visibility of the object
 *     visibility?: '0'|'1',
 *     // Whether the object is assignable
 *     assignable?: '0'|'1',
 *     // Conversation token
 *     conversation?: string,
 *     // ETag of the object for caching
 *     etag?: string,
 *     // Permissions for the file
 *     permissions?: string,
 *     // Width of the object (e.g. image, video)
 *     width?: string,
 *     // Height of the object (e.g. image, video)
 *     height?: string,
 *     // Blurhash of the image
 *     blurhash?: string,
 * }
 *
 * @psalm-type TalkBaseMessage = array{
 *     // Display name of the message author (can be empty for type `deleted_users` and `guests`)
 *     actorDisplayName: string,
 *     // Actor id of the message author
 *     actorId: string,
 *     // See [Constants - Actor types of chat messages](https://nextcloud-talk.readthedocs.io/en/latest/constants#actor-types-of-chat-messages)
 *     actorType: string,
 *     // Unix time stamp when the message expires and should be removed from the clients UI without further note or warning (only available with `message-expiration` capability)
 *     expirationTimestamp: int,
 *     // Message string with placeholders (see [Rich Object String](https://github.com/nextcloud/server/issues/1706))
 *     message: string,
 *     // Message parameters for `message` (see [Rich Object String](https://github.com/nextcloud/server/issues/1706))
 *     messageParameters: array<string, TalkRichObjectParameter>,
 *     // Currently known types are `comment`, `comment_deleted`, `system` and `command`
 *     messageType: string,
 *     // Empty for normal chat message or the type of the system message (untranslated)
 *     systemMessage: string,
 *  }
 *
 * @psalm-type TalkChatMessageMetaData = array{
 *     // Actor type of the attendee that pinned the message - Required capability: `pinned-messages`
 *     pinnedActorType?: string,
 *     // Actor ID of the attendee that pinned the message - Required capability: `pinned-messages`
 *     pinnedActorId?: string,
 *     // Display name of the attendee that pinned the message - Required capability: `pinned-messages`
 *     pinnedActorDisplayName?: string,
 *     // Timestamp when the message was pinned - Required capability: `pinned-messages`
 *     pinnedAt?: int,
 *     // Timestamp until when the message is pinned. If missing the message is pinned infinitely - Required capability: `pinned-messages`
 *     pinnedUntil?: int,
 *     // Set when a thread is created with this message. If missing, no thread creation is associated with this message
 *     threadId?: int,
 *     // Set when a thread is created with this message. If missing, no thread creation is associated with this message
 *     threadTitle?: string,
 * }
 *
 * @psalm-type TalkChatMessage = TalkBaseMessage&array{
 *     // Set to `true` when the message was deleted
 *     deleted?: true,
 *     // ID of the comment
 *     id: int,
 *     // True if the user can post a reply to this message (only available with `chat-replies` capability)
 *     isReplyable: bool,
 *     // Whether the message should be rendered as markdown or shown as plain text
 *     markdown: bool,
 *     // An array map with relation between reaction emoji and total count of reactions with this emoji
 *     reactions: array<string, integer>|\stdClass,
 *     // When the user reacted this is the list of emojis the user reacted with
 *     reactionsSelf?: list<string>,
 *     // A reference string that was given while posting the message to be able to identify a sent message again (only available with `chat-reference-id` capability)
 *     referenceId: string,
 *     // Timestamp in seconds and UTC time zone
 *     timestamp: int,
 *     // Conversation token
 *     token: string,
 *     // Display name of the last editing author (only available with `edit-messages` capability and when the message was actually edited)
 *     lastEditActorDisplayName?: string,
 *     // Actor id of the last editing author (only available with `edit-messages` capability and when the message was actually edited)
 *     lastEditActorId?: string,
 *     // Actor type of the last editing author - See [Constants - Actor types of chat messages](https://nextcloud-talk.readthedocs.io/en/latest/constants#actor-types-of-chat-messages) (only available with `edit-messages` capability and when the message was actually edited)
 *     lastEditActorType?: string,
 *     // Unix time stamp when the message was last edited (only available with `edit-messages` capability and when the message was actually edited)
 *     lastEditTimestamp?: int,
 *     // Whether the message was sent silently (only available with `silent-send-state` capability)
 *     silent?: bool,
 *     // Thread ID if this message is part of a thread
 *     threadId?: int,
 *     // Whether this message is the root of a thread
 *     isThread?: bool,
 *     // Title of the thread if this message is the root of a thread
 *     threadTitle?: string,
 *     // Number of replies in the thread if this message is the root of a thread
 *     threadReplies?: int,
 *     // Additional metadata of the message
 *     metaData?: TalkChatMessageMetaData,
 * }
 *
 * @psalm-type TalkChatProxyMessage = TalkBaseMessage
 *
 * @psalm-type TalkRoomLastMessage = TalkChatMessage|TalkChatProxyMessage
 *
 * @psalm-type TalkDeletedChatMessage = array{
 *     // ID of the parent comment
 *     id: int,
 *     // `true` when the parent is deleted
 *     deleted: true,
 * }
 *
 * @psalm-type TalkChatMessageWithParent = TalkChatMessage&array{parent?: TalkChatMessage|TalkDeletedChatMessage}
 *
 * @psalm-type TalkChatReminder = array{
 *     // ID of the message the reminder is for
 *     messageId: int,
 *     // UNIX timestamp when the reminder should trigger
 *     timestamp: int,
 *     // Conversation token
 *     token: string,
 *     // User id of the user that set the reminder
 *     userId: string,
 * }
 *
 * @psalm-type TalkChatReminderUpcoming = array{
 *     // Display name of the message author
 *     actorDisplayName: string,
 *     // Actor id of the message author
 *     actorId: string,
 *     // Actor type of the message author
 *     actorType: string,
 *     // Message string with placeholders
 *     message: string,
 *     // ID of the message the reminder is for
 *     messageId: int,
 *     // Message parameters for `message` (see [Rich Object String](https://github.com/nextcloud/server/issues/1706))
 *     messageParameters: array<string, TalkRichObjectParameter>,
 *     // UNIX timestamp when the reminder should trigger
 *     reminderTimestamp: int,
 *     // Conversation token
 *     roomToken: string,
 * }
 *
 * @psalm-type TalkFederationInvite = array{
 *     // Identifier of the invitation
 *     id: int,
 *     // State of the invitation
 *     state: int,
 *     // Cloud ID of the local user
 *     localCloudId: string,
 *     // Token of the local conversation
 *     localToken: string,
 *     // Attendee ID on the remote server
 *     remoteAttendeeId: int,
 *     // URL of the remote server
 *     remoteServerUrl: string,
 *     // Token on the remote server
 *     remoteToken: string,
 *     // Name of the conversation on the remote server
 *     roomName: string,
 *     // User id of the invited user
 *     userId: string,
 *     // Cloud ID of the inviter
 *     inviterCloudId: string,
 *     // Display name of the inviter
 *     inviterDisplayName: string,
 * }
 *
 * @psalm-type TalkMatterbridgeConfigFields = list<array<string, mixed>>
 *
 * @psalm-type TalkMatterbridge = array{
 *     // Whether the bridge is enabled
 *     enabled: bool,
 *     // Bridge configuration parts
 *     parts: TalkMatterbridgeConfigFields,
 *     // Process ID of the Matterbridge process
 *     pid: int,
 * }
 *
 * @psalm-type TalkMatterbridgeProcessState = array{
 *     // Log output of the Matterbridge process
 *     log: string,
 *     // Whether the Matterbridge process is running
 *     running: bool,
 * }
 *
 * @psalm-type TalkMatterbridgeWithProcessState = TalkMatterbridge&TalkMatterbridgeProcessState
 *
 * @psalm-type TalkParticipant = array{
 *     // The unique identifier for the given actor type
 *     actorId: string,
 *     // The cloud id of the invited user
 *     invitedActorId?: string,
 *     // Currently known `users|guests|emails|groups|circles` (see [Constants - Attendee types](https://nextcloud-talk.readthedocs.io/en/latest/constants#attendee-types))
 *     actorType: string,
 *     // Unique attendee id
 *     attendeeId: int,
 *     // Dedicated permissions for the current participant, if not `Custom` this are not the resulting permissions (see [constants list](https://nextcloud-talk.readthedocs.io/en/latest/constants#attendee-permissions))
 *     attendeePermissions: int,
 *     // Unique dial-in authentication code for this user, when the conversation has SIP enabled (see `sipEnabled` attribute)
 *     attendeePin: string,
 *     // Can be empty for guests
 *     displayName: string,
 *     // Call flags the user joined with (see [constants list](https://nextcloud-talk.readthedocs.io/en/latest/constants#participant-in-call-flag))
 *     inCall: int,
 *     // Timestamp of the last ping of the user (should be used for sorting)
 *     lastPing: int,
 *     // Permissions level of the participant (see [constants list](https://nextcloud-talk.readthedocs.io/en/latest/constants#participant-types))
 *     participantType: int,
 *     // Combined final permissions for the participant, permissions are picked in order of attendee then call then default and the first which is `Custom` will apply (see [constants list](https://nextcloud-talk.readthedocs.io/en/latest/constants#attendee-permissions))
 *     permissions: int,
 *     // Only available with `breakout-rooms-v1` capability
 *     roomToken: string,
 *     // Array of session ids, each are up to 512 character long strings, or empty if no session
 *     sessionIds: list<string>,
 *     // Only available with `includeStatus=true`, for users with a set status and when there are less than 100 participants in the conversation
 *     status?: string,
 *     // Only available with `includeStatus=true`, for users with a set status and when there are less than 100 participants in the conversation
 *     statusClearAt?: ?int,
 *     // Only available with `includeStatus=true`, for users with a set status and when there are less than 100 participants in the conversation
 *     statusIcon?: ?string,
 *     // Only available with `includeStatus=true`, for users with a set status and when there are less than 100 participants in the conversation
 *     statusMessage?: ?string,
 *     // Only available with `sip-support-dialout` capability and only filled for moderators that are allowed to configure SIP for conversations
 *     phoneNumber?: ?string,
 *     // Only available with `sip-support-dialout` capability and only filled for moderators that are allowed to configure SIP for conversations
 *     callId?: ?string,
 * }
 *
 * @psalm-type TalkPollVote = array{
 *     // The display name of the participant that voted
 *     actorDisplayName: string,
 *     // The actor id of the participant that voted
 *     actorId: string,
 *     // The actor type of the participant that voted (see [Constants - Attendee types](https://nextcloud-talk.readthedocs.io/en/latest/constants#attendee-types))
 *     actorType: string,
 *     // The option that was voted for
 *     optionId: int,
 *  }
 *
 * @psalm-type TalkPollDraft = array{
 *     // Display name of the poll author
 *     actorDisplayName: string,
 *     // Actor ID identifying the poll author
 *     actorId: non-empty-string,
 *     // Actor type of the poll author (see [Constants - Attendee types](https://nextcloud-talk.readthedocs.io/en/latest/constants#attendee-types))
 *     actorType: TalkActorTypes,
 *     // ID of the poll
 *     id: int<1, max>,
 *     // Maximum amount of options a user can vote for, `0` means unlimited
 *     maxVotes: int<0, max>,
 *     // The options participants can vote for
 *     options: list<string>,
 *     // The question of the poll
 *     question: non-empty-string,
 *     // Result mode of the poll (see [Constants - Poll mode](https://nextcloud-talk.readthedocs.io/en/latest/constants#poll-mode))
 *     resultMode: 0|1,
 *     // Status of the poll (see [Constants - Poll status](https://nextcloud-talk.readthedocs.io/en/latest/constants#poll-status))
 *     status: 0|1|2,
 * }
 *
 * @psalm-type TalkPoll = TalkPollDraft&array{
 *     // Detailed list who voted for which option (only available for public closed polls)
 *     details?: list<TalkPollVote>,
 *     // The number of unique voters that voted (only available when the actor voted on public poll or the poll is closed unless for the creator and moderators)
 *     numVoters?: int<0, max>,
 *     // Array of option ids the participant voted for
 *     votedSelf?: list<int>,
 *     // Map with `'option-' + optionId` => number of votes (only available when the actor voted on public poll or the poll is closed)
 *     votes?: array<string, int>,
 * }
 *
 * @psalm-type TalkReaction = array{
 *     // Display name of the reaction author
 *     actorDisplayName: string,
 *     // Actor id of the reacting participant
 *     actorId: string,
 *     // `guests` or `users`
 *     actorType: string,
 *     // Timestamp in seconds and UTC time zone
 *     timestamp: int,
 * }
 *
 * @psalm-type TalkInvitationList = array{
 *     // List of user ids that could not be invited
 *     users?: list<string>,
 *     // List of federated user ids that could not be invited
 *     federated_users?: list<string>,
 *     // List of group ids that could not be invited
 *     groups?: list<string>,
 *     // List of email addresses that could not be invited
 *     emails?: list<string>,
 *     // List of phone numbers that could not be invited
 *     phones?: list<string>,
 *     // List of team ids that could not be invited
 *     teams?: list<string>,
 * }
 *
 * @psalm-type TalkRoom = array{
 *     // The unique identifier for the given actor type
 *     actorId: string,
 * 		// The cloud id of the invited user
 *     invitedActorId?: string,
 * 	   // Actor type of the current user (see [constants list](https://nextcloud-talk.readthedocs.io/en/latest/constants#attendee-types))
 *     actorType: string,
 *     // Unique attendee id
 *     attendeeId: int,
 *     // Dedicated permissions for the current participant, if not `Custom` this are not the resulting permissions (see [constants list](https://nextcloud-talk.readthedocs.io/en/latest/constants#attendee-permissions))
 *     attendeePermissions: int,
 *     // Unique dial-in authentication code for this user, when the conversation has SIP enabled (see `sipEnabled` attribute)
 *     attendeePin: ?string,
 *     // Version of conversation avatar used to easier expiration of the avatar in case a moderator updates it, since the avatar endpoint should be cached for 24 hours. (only available with `avatar` capability)
 *     avatarVersion: string,
 *     // Breakout room configuration mode (see [constants list](https://nextcloud-talk.readthedocs.io/en/latest/constants#breakout-room-modes)) (only available with `breakout-rooms-v1` capability)
 *     breakoutRoomMode: int,
 *     // Breakout room status (see [constants list](https://nextcloud-talk.readthedocs.io/en/latest/constants#breakout-room-status)) (only available with `breakout-rooms-v1` capability)
 *     breakoutRoomStatus: int,
 *     // Combined flag of all participants in the current call (see [constants list](https://nextcloud-talk.readthedocs.io/en/latest/constants#participant-in-call-flag), only available with `conversation-call-flags` capability)
 *     callFlag: int,
 *     // Call permissions, if not `Custom` this are not the resulting permissions, if set they will reset after the end of the call (see [constants list](https://nextcloud-talk.readthedocs.io/en/latest/constants#attendee-permissions))
 *     callPermissions: int,
 *     // Type of call recording (see [Constants - Call recording status](https://nextcloud-talk.readthedocs.io/en/latest/constants#call-recording-status)) (only available with `recording-v1` capability)
 *     callRecording: 0|1|2|3|4|5,
 *     // Timestamp when the call was started (only available with `recording-v1` capability)
 *     callStartTime: int,
 *     // Flag if the user can delete the conversation for everyone (not possible without moderator permissions or in one-to-one conversations)
 *     canDeleteConversation: bool,
 * 	   // Whether the given user can enable SIP for this conversation. Note that when the token is not-numeric only, SIP can not be enabled even if the user is permitted and a moderator of the conversation
 *     canEnableSIP: bool,
 *     // Flag if the user can leave the conversation (not possible for the last user with moderator permissions)
 *     canLeaveConversation: bool,
 *     // Flag if the user can start a new call in this conversation (joining is always possible) (only available with `start-call-flag` capability)
 *     canStartCall: bool,
 *     // Default permissions for new participants (see [constants list](https://nextcloud-talk.readthedocs.io/en/latest/constants#attendee-permissions))
 *     defaultPermissions: int,
 *     // Description of the conversation (can also be empty) (only available with `room-description` capability)
 *     description: string,
 *     // `name` if non-empty, otherwise it falls back to a list of participants
 *     displayName: string,
 *     // Flag if the conversation has an active call
 *     hasCall: bool,
 *     // Flag if the conversation has a password
 *     hasPassword: bool,
 *     // Identifier of the conversation
 *     id: int,
 *     // Flag if the conversation has a custom avatar (only available with `avatar` capability)
 *     isCustomAvatar: bool,
 *     // Flag if the conversation is favorited by the user
 *     isFavorite: bool,
 *     // Timestamp of the last activity in the conversation, in seconds and UTC time zone
 *     lastActivity: int,
 *     // ID of the last message read by every user that has read privacy set to public in a room. When the user themself has it set to private the value is `0` (only available with `chat-read-status` capability)
 *     lastCommonReadMessage: int,
 * 	   // Last message in a conversation if available, otherwise empty. **Note:** Even when given the message will not contain the `parent` or `reactionsSelf` attribute due to performance reasons
 *     lastMessage?: TalkRoomLastMessage,
 *     // Timestamp of the user's session making the request
 *     lastPing: int,
 *     // ID of the last read message in a room (only available with `chat-read-marker` capability)
 *     lastReadMessage: int,
 *     // Listable scope for the room (see [constants list](https://nextcloud-talk.readthedocs.io/en/latest/constants#listable-scope)) (only available with `listable-rooms` capability)
 *     listable: int,
 *     // ID of the language to use for live transcriptions in the room,
 *     liveTranscriptionLanguageId: string,
 *     // Webinar lobby restriction (0-1), if the participant is a moderator they can always join the conversation (only available with `webinary-lobby` capability) (See [Webinar lobby states](https://nextcloud-talk.readthedocs.io/en/latest/constants#webinar-lobby-states))
 *     lobbyState: int,
 *     // Timestamp when the lobby will be automatically disabled (only available with `webinary-lobby` capability)
 *     lobbyTimer: int,
 *     // Whether all participants can mention using `@all` or only moderators (see [constants list](https://nextcloud-talk.readthedocs.io/en/latest/constants#mention-permissions)) (only available with `mention-permissions` capability)
 *     mentionPermissions: 0|1,
 *     // The message expiration time in seconds in this chat. Zero if disabled. (only available with `message-expiration` capability)
 *     messageExpiration: int,
 *     // Name of the conversation (can also be empty)
 *     name: string,
 *     // The call notification level for the user (see [Participant call notification levels](https://nextcloud-talk.readthedocs.io/en/latest/constants#participant-call-notification-levels))
 *     notificationCalls: int,
 *     // The notification level for the user (See [Participant notification levels](https://nextcloud-talk.readthedocs.io/en/latest/constants#participant-notification-levels))
 *     notificationLevel: int,
 *     // See [Object types](https://nextcloud-talk.readthedocs.io/en/latest/constants#object-types) documentation for explanation
 *     objectId: string,
 *     // The type of object that the conversation is associated with (See [Object types](https://nextcloud-talk.readthedocs.io/en/latest/constants#object-types))
 *     objectType: string,
 *     // "In call" flags of the user's session making the request (see [constants list](https://nextcloud-talk.readthedocs.io/en/latest/constants#participant-in-call-flag)) (only available with `in-call-flags` capability)
 *     participantFlags: int,
 * 	   // Permissions level of the current user (see [constants list](https://nextcloud-talk.readthedocs.io/en/latest/constants#participant-types))
 *     participantType: int,
 *     // Combined final permissions for the current participant, permissions are picked in order of attendee then call then default and the first which is `Custom` will apply (see [constants list](https://nextcloud-talk.readthedocs.io/en/latest/constants#attendee-permissions))
 *     permissions: int,
 *     // Read-only state for the current user (see [constants list](https://nextcloud-talk.readthedocs.io/en/latest/constants#read-only-states)) (only available with `read-only-rooms` capability)
 *     readOnly: int,
 *     // Whether recording consent is required before joining a call (Only 0 and 1 will be returned, see [constants list](https://nextcloud-talk.readthedocs.io/en/latest/constants#recording-consent-required)) (only available with `recording-consent` capability)
 *     recordingConsent: int,
 *     remoteServer?: string,
 *     remoteToken?: string,
 *     // `'0'` if not connected, otherwise an up to 512 character long string that is the identifier of the user's session making the request. Should only be used to pre-check if the user joined already with this session, but this might be outdated by the time of usage, so better check via [Get list of participants in a conversation](https://nextcloud-talk.readthedocs.io/en/latest/participant/#get-list-of-participants-in-a-conversation)
 *     sessionId: string,
 *     // SIP enable status (see [constants list](https://nextcloud-talk.readthedocs.io/en/latest/constants#sip-states))
 *     sipEnabled: int,
 *     // Only available for one-to-one conversations, when `includeStatus=true` is set and the user has a status
 *     status?: string,
 *     // Only available for one-to-one conversations, when `includeStatus=true` is set and the user has a status, can still be null even with a status
 *     statusClearAt?: ?int,
 *     // Only available for one-to-one conversations, when `includeStatus=true` is set and the user has a status, can still be null even with a status
 *     statusIcon?: ?string,
 *     // Only available for one-to-one conversations, when `includeStatus=true` is set and the user has a status, can still be null even with a status
 *     statusMessage?: ?string,
 * 	   // Token identifier of the conversation which is used for further interaction
 *     token: string,
 *     // See list of conversation types in the [constants list](https://nextcloud-talk.readthedocs.io/en/latest/constants/#conversation-types)
 *     type: int,
 *     // Flag if the user was mentioned since their last visit
 *     unreadMention: bool,
 *     // Flag if the user was mentioned directly (ignoring `@all` mentions) since their last visit (only available with `direct-mention-flag` capability)
 *     unreadMentionDirect: bool,
 *     // Number of unread chat messages in the conversation (only available with `chat-v2` capability)
 *     unreadMessages: int,
 *     // Flag if the conversation is archived by the user (only available with `archived-conversations-v2` capability)
 *     isArchived: bool,
 *     // Required capability: `important-conversations`
 *     isImportant: bool,
 *     // Required capability: `sensitive-conversations`
 *     isSensitive: bool,
 *     // Required capability: `pinned-messages`
 *     lastPinnedId: int,
 *     // Required capability: `pinned-messages`
 *     hiddenPinnedId: int,
 *     // Required capability: `scheduled-messages` (local)
 *     hasScheduledMessages: int,
 *     // Bit-flag of enabled attributes of this conversation (only available with capability: `conversation-attributes`). See [attributes list](https://nextcloud-talk.readthedocs.io/en/latest/constants/#conversation-attributes) for details
 *     attributes: int,
 * }
 *
 * @psalm-type TalkDashboardEventAttachment = array{
 *      // List of calendar principal URIs this attachment belongs to
 *      calendars: non-empty-list<string>,
 *      // MIME type of the attachment
 *      fmttype: string,
 *      // File name of the attachment
 *      filename: string,
 *      // File id of the attachment
 *      fileid: int,
 *      // Whether a preview is available for the attachment
 *      preview: boolean,
 *      // Link to the preview of the attachment
 *      previewLink: ?string,
 * }
 *
 * @psalm-type TalkDashboardEventCalendar = array{
 *     // Principal URI of the calendar owner
 *     principalUri: string,
 *     // Name of the calendar
 *     calendarName: string,
 *     // Color of the calendar
 *     calendarColor: ?string,
 * }
 *
 * @psalm-type TalkDashboardEvent = array{
 *     // List of calendars this event belongs to
 *     calendars: non-empty-list<TalkDashboardEventCalendar>,
 *     // Name of the event
 *     eventName: string,
 *     // Description of the event
 *     eventDescription: ?string,
 *     // Attachments of the event
 *     eventAttachments: array<string, TalkDashboardEventAttachment>,
 *     // Link to the event
 *     eventLink: string,
 *     // UNIX timestamp of the event start
 *     start: int,
 *     // UNIX timestamp of the event end
 *     end: int,
 *     // Conversation token
 *     roomToken: string,
 *     // Version of conversation avatar for caching
 *     roomAvatarVersion: string,
 *     // Name of the conversation
 *     roomName: string,
 *     // Display name of the conversation
 *     roomDisplayName: string,
 *     // Type of the conversation (see [constants list](https://nextcloud-talk.readthedocs.io/en/latest/constants#conversation-types))
 *     roomType: int,
 *     // UNIX timestamp since when a call is active, or null if no call
 *     roomActiveSince: ?int,
 *     // Number of invited attendees
 *     invited: ?int,
 *     // Number of accepted attendees
 *     accepted: ?int,
 *     // Number of tentative attendees
 *     tentative: ?int,
 *     // Number of declined attendees
 *     declined: ?int,
 * }
 *
 * @psalm-type TalkRoomWithInvalidInvitations = TalkRoom&array{
 *     // List of participants that could not be invited grouped by source type
 *     invalidParticipants: TalkInvitationList,
 * }
 *
 * @psalm-type TalkSignalingSession = array{
 *     // The unique identifier for the given actor type
 *     actorId: string,
 *     // Actor type of the attendee (see [Constants - Attendee types](https://nextcloud-talk.readthedocs.io/en/latest/constants#attendee-types))
 *     actorType: string,
 *     // Call flags the user joined with (see [constants list](https://nextcloud-talk.readthedocs.io/en/latest/constants#participant-in-call-flag))
 *     inCall: int,
 *     // Timestamp of the last ping of the user
 *     lastPing: int,
 *     // Combined final permissions for the participant (see [constants list](https://nextcloud-talk.readthedocs.io/en/latest/constants#attendee-permissions))
 *     participantPermissions: int,
 *     // Identifier of the conversation
 *     roomId: int,
 *     // up to 512 character long string
 *     sessionId: string,
 *     // User id of the participant (empty for guests)
 *     userId: string,
 * }
 *
 * @psalm-type TalkSignalingFederationSettings = array{
 *     // URL of the signaling server for federation
 *     server: string,
 *     // URL of the Nextcloud server for federation
 *     nextcloudServer: string,
 *     // Authentication parameters for the hello request
 *     helloAuthParams: array{
 *         token: string,
 *     },
 *     // Room id on the federated server
 *     roomId: string,
 * }
 *
 * @psalm-type TalkSignalingSettings = array{
 *     // Federation signaling settings, or null if not federated
 *     federation: TalkSignalingFederationSettings|null,
 *     // Authentication parameters for the hello request
 *     helloAuthParams: array{
 *         "1.0": array{
 *             userid: ?string,
 *             ticket: string,
 *         },
 *         "2.0": array{
 *             token: string,
 *         },
 *     },
 *     // Whether the warning about the signaling server should be hidden
 *     hideWarning: bool,
 *     // URL of the signaling server
 *     server: string,
 *     // Signaling mode (see [constants list](https://nextcloud-talk.readthedocs.io/en/latest/constants#signaling-modes))
 *     signalingMode: string,
 *     // SIP dial-in information
 *     sipDialinInfo: string,
 *     // STUN servers
 *     stunservers: list<array{urls: list<string>}>,
 *     // Authentication ticket for the signaling server
 *     ticket: string,
 *     // TURN servers
 *     turnservers: list<array{urls: list<string>, username: string, credential: mixed}>,
 *     // User id of the current user
 *     userId: ?string,
 * }
 *
 * @psalm-type TalkThread = array{
 *     // Identifier of the thread
 *     id: positive-int,
 *     // Conversation token
 *     roomToken: string,
 *     // Title of the thread
 *     title: string,
 *     // ID of the last message in the thread
 *     lastMessageId: non-negative-int,
 *     // UNIX timestamp of the last activity in the thread
 *     lastActivity: non-negative-int,
 *     // Number of replies in the thread
 *     numReplies: non-negative-int,
 * }
 *
 * @psalm-type TalkThreadAttendee = array{
 *      // The notification level for the user in this thread (See [Participant notification levels](https://nextcloud-talk.readthedocs.io/en/latest/constants#participant-notification-levels))
 *      notificationLevel: 0|1|2|3,
 * }
 *
 * @psalm-type TalkThreadInfo = array{
 *      // Thread details
 *      thread: TalkThread,
 *      // Attendee details for the current user in this thread
 *      attendee: TalkThreadAttendee,
 *      // First message in the thread (root message)
 *      first: ?TalkChatMessage,
 *      // Last message in the thread
 *      last: ?TalkChatMessage,
 * }
 *
 * @psalm-type TalkCapabilities = array{
 *     // List of features available on the server
 *     features: non-empty-list<string>,
 *     // List of features only available locally (not for federated conversations)
 *     features-local: non-empty-list<string>,
 *     config: array{
 *         attachments: array{
 *             // Whether file sharing is allowed in conversations
 *             allowed: bool,
 *             // User's attachment folder (only available for logged in users)
 *             folder?: string,
 *         },
 *         call: array{
 *             // Whether calls are enabled
 *             enabled: bool,
 *             // Whether breakout rooms are enabled
 *             breakout-rooms: bool,
 *             // Whether call recording is enabled
 *             recording: bool,
 *             // Whether recording consent is required (see [constants list](https://nextcloud-talk.readthedocs.io/en/latest/constants#recording-consent-required))
 *             recording-consent: int,
 *             // List of supported reaction emojis during calls
 *             supported-reactions: list<string>,
 *             // List of file names relative to the spreed/img/backgrounds/ web path, e.g. `2_home.jpg`
 *             predefined-backgrounds: list<string>,
 *             // List of file paths relative to the server web root with leading slash, e.g. `/apps/spreed/img/backgrounds/2_home.jpg`
 *             predefined-backgrounds-v2: list<string>,
 *             // Whether the user can upload custom virtual backgrounds
 *             can-upload-background: bool,
 *             // Whether SIP is enabled on the server
 *             sip-enabled: bool,
 *             // Whether SIP dial-out is enabled on the server
 *             sip-dialout-enabled: bool,
 *             // Default phone region of the server
 *             default-phone-region: string,
 *             // Whether the user can enable SIP for conversations
 *             can-enable-sip: bool,
 *             // Whether calls start without media by default
 *             start-without-media: bool,
 *             // Maximum duration of a call in seconds, `0` means unlimited
 *             max-duration: int,
 *             // Whether the blur virtual background is available
 *             blur-virtual-background: bool,
 *             // Whether end-to-end encryption is available
 *             end-to-end-encryption: bool,
 *             // Whether live transcription is available
 *             live-transcription: bool,
 *             // Whether live translation is available
 *             live-translation: bool,
 *             // The default target language for live transcription
 *             live-transcription-target-language-id: string,
 *             // Whether to play sounds for call events
 *             play-sounds: bool,
 *             // Maximum number of participants shown in the grid view
 *             grid-limit: int,
 *             // Whether the grid limit is enforced by the server
 *             grid-limit-enforced: bool,
 *         },
 *         chat: array{
 *             // Maximum length of a chat message
 *             max-length: int,
 *             // Read privacy setting for the user (see [constants list](https://nextcloud-talk.readthedocs.io/en/latest/constants#participant-read-status-privacy))
 *             read-privacy: int,
 *             // Whether translation providers are available
 *             has-translation-providers: bool,
 *             // Whether translation task providers are available
 *             has-translation-task-providers: bool,
 *             // Typing privacy setting for the user (see [constants list](https://nextcloud-talk.readthedocs.io/en/latest/constants#participant-typing-privacy))
 *             typing-privacy: int,
 *             // Minimum number of chat messages before a summary can be generated
 *             summary-threshold: positive-int,
 *             // Chat message rendering style (see [constants list](https://nextcloud-talk.readthedocs.io/en/latest/constants#chat-style))
 *             style: 'split'|'unified',
 *             // Whether Matterbridge is enabled
 *             matterbridge-enabled: bool,
 *         },
 *         conversations: array{
 *             // Whether the user can create conversations
 *             can-create: bool,
 *             // Whether passwords are enforced for public conversations
 *             force-passwords: bool,
 *             // Conversation list style (see [constants list](https://nextcloud-talk.readthedocs.io/en/latest/constants#conversation-list-style))
 *             list-style: 'two-lines'|'compact',
 *             // Maximum length of a conversation description
 *             description-length: positive-int,
 *             // Retention period for event conversations in seconds, `0` means no retention
 *             retention-event: non-negative-int,
 *             // Retention period for phone conversations in seconds, `0` means no retention
 *             retention-phone: non-negative-int,
 *             // Retention period for instant meetings in seconds, `0` means no retention
 *             retention-instant-meetings: non-negative-int,
 *         },
 *         federation: array{
 *             // Whether federation is enabled
 *             enabled: bool,
 *             // Whether incoming federation is enabled
 *             incoming-enabled: bool,
 *             // Whether outgoing federation is enabled
 *             outgoing-enabled: bool,
 *             // Whether only trusted servers are allowed for federation
 *             only-trusted-servers: bool,
 *         },
 *         previews: array{
 *             // Maximum GIF file size in bytes for previews
 *             max-gif-size: int,
 *         },
 *         signaling: array{
 *             // Maximum number of sessions that can be pinged in a single request
 *             session-ping-limit: int,
 *             // Signaling mode (see [constants list](https://nextcloud-talk.readthedocs.io/en/latest/constants#signaling-modes))
 *             mode: 'internal'|'external'|'conversation_cluster',
 *             // Public key for hello v2 authentication
 *             hello-v2-token-key?: string,
 *         },
 *         experiments: array{
 *             // Bit-flag of enabled experiments
 *             enabled: non-negative-int,
 *         },
 *         'feature-hints': array{
 *             current: positive-int,
 *             hidden: non-negative-int,
 *         },
 *         permissions: array{
 *             // Maximum default permissions (see [constants list](https://nextcloud-talk.readthedocs.io/en/latest/constants#attendee-permissions))
 *             max-default: int,
 *             // Maximum custom permissions (see [constants list](https://nextcloud-talk.readthedocs.io/en/latest/constants#attendee-permissions))
 *             max-custom: int,
 *             // Server default permissions (see [constants list](https://nextcloud-talk.readthedocs.io/en/latest/constants#attendee-permissions))
 *             default: int,
 *         },
 *     },
 *     // Map of config keys that are only available locally (not for federated conversations)
 *     config-local: array<string, list<string>>,
 *     // Version of the Talk app
 *     version: string,
 * }
 *
 * @psalm-type TalkLiveTranscriptionLanguage = array{
 *     // Name of the language
 *     name: string,
 *     // Metadata of the language
 *     metadata: array{
 *         // Word separator character
 *         separator: string,
 *         // Whether the language is right-to-left
 *         rtl: bool,
 *     },
 * }
 *
 * @psalm-type TalkScheduledMessage = array{
 *     // SnowflakeID
 *     id: numeric-string,
 *     // Actor id of the message author
 *     actorId: string,
 *     // Actor type of the message author
 *     actorType: string,
 *     // Thread ID if the scheduled message is for a thread
 *     threadId: int,
 *     // Title of the thread if the scheduled message is for a thread
 *     threadTitle?: string,
 *     // Parent message if the scheduled message is a reply
 *     parent?: TalkChatMessage,
 *     // Message string with placeholders
 *     message: string,
 *     // Currently known types are `comment`, `comment_deleted`, `system` and `command`
 *     messageType: string,
 *     // UNIX timestamp when the scheduled message was created
 *     createdAt: int,
 *     // UNIX timestamp when the message should be sent
 *     sendAt: int,
 *     // Whether the message should be sent silently without creating chat notifications
 *     silent: bool,
 *     // Set only if sending failed to persist the original timestamp and expose it
 *     originalSendAt?: int,
 * }
 *
 * @psalm-type TalkConversationPreset = array{
 *     // Identifier of the preset, currently known: default, forced, webinar, presentation, hallway
 *     identifier: string,
 *     // Translated name of the preset in user's language
 *     name: string,
 *     // Translated description of the preset in user's language
 *     description: string,
 *     // List of parameters that should be set
 *     parameters: array<string, int>,
 * }
 */
class ResponseDefinitions {
}
