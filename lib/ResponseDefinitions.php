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
 *     id: int,
 *     moderatorActorType: string,
 *     moderatorActorId: string,
 *     moderatorDisplayName: string,
 *     bannedActorType: string,
 *     bannedActorId: string,
 *     bannedDisplayName: string,
 *     bannedTime: int,
 *     internalNote: string,
 * }
 *
 * @psalm-type TalkBot = array{
 *     description: ?string,
 *     id: int,
 *     name: string,
 *     state: int,
 * }
 *
 * @psalm-type TalkBotWithDetails = TalkBot&array{
 *     error_count: int,
 *     features: int,
 *     last_error_date: int,
 *     last_error_message: string,
 *     url: string,
 *     url_hash: string,
 * }
 *
 * @psalm-type TalkCallPeer = array{
 *     actorId: string,
 *     actorType: string,
 *     displayName: string,
 *     lastPing: int,
 *     sessionId: string,
 *     token: string,
 * }
 *
 * @psalm-type TalkChatMentionSuggestion = array{
 *     id: string,
 *     label: string,
 *     source: string,
 *     mentionId: string,
 *     details?: string,
 *     status?: string,
 *     statusClearAt?: ?int,
 *     statusIcon?: ?string,
 *     statusMessage?: ?string,
 * }
 *
 * @psalm-type TalkRichObjectParameter = array{
 *     type: string,
 *     id: string,
 *     name: string,
 *     server?: string,
 *     link?: string,
 *     'call-type'?: 'one2one'|'group'|'public',
 *     'icon-url'?: string,
 *     'message-id'?: string,
 *     boardname?: string,
 *     stackname?: string,
 *     size?: string,
 *     path?: string,
 *     mimetype?: string,
 *     'preview-available'?: 'yes'|'no',
 *     'hide-download'?: 'yes'|'no',
 *     mtime?: string,
 *     latitude?: string,
 *     longitude?: string,
 *     description?: string,
 *     thumb?: string,
 *     website?: string,
 *     visibility?: '0'|'1',
 *     assignable?: '0'|'1',
 *     conversation?: string,
 *     etag?: string,
 *     permissions?: string,
 *     width?: string,
 *     height?: string,
 *     blurhash?: string,
 * }
 *
 * @psalm-type TalkBaseMessage = array{
 *     actorDisplayName: string,
 *     actorId: string,
 *     actorType: string,
 *     expirationTimestamp: int,
 *     message: string,
 *     messageParameters: array<string, TalkRichObjectParameter>,
 *     messageType: string,
 *     systemMessage: string,
 *  }
 *
 * @psalm-type TalkChatMessage = TalkBaseMessage&array{
 *     deleted?: true,
 *     id: int,
 *     isReplyable: bool,
 *     markdown: bool,
 *     reactions: array<string, integer>|\stdClass,
 *     reactionsSelf?: list<string>,
 *     referenceId: string,
 *     timestamp: int,
 *     token: string,
 *     lastEditActorDisplayName?: string,
 *     lastEditActorId?: string,
 *     lastEditActorType?: string,
 *     lastEditTimestamp?: int,
 *     silent?: bool,
 *     threadId?: int,
 *     isThread?: bool,
 *     threadTitle?: string,
 *     threadReplies?: int,
 * }
 *
 * @psalm-type TalkChatProxyMessage = TalkBaseMessage
 *
 * @psalm-type TalkRoomLastMessage = TalkChatMessage|TalkChatProxyMessage
 *
 * @psalm-type TalkDeletedChatMessage = array{
 *     id: int,
 *     deleted: true,
 * }
 *
 * @psalm-type TalkChatMessageWithParent = TalkChatMessage&array{parent?: TalkChatMessage|TalkDeletedChatMessage}
 *
 * @psalm-type TalkChatReminder = array{
 *     messageId: int,
 *     timestamp: int,
 *     token: string,
 *     userId: string,
 * }
 *
 * @psalm-type TalkChatReminderUpcoming = array{
 *     actorDisplayName: string,
 *     actorId: string,
 *     actorType: string,
 *     message: string,
 *     messageId: int,
 *     messageParameters: array<string, TalkRichObjectParameter>,
 *     reminderTimestamp: int,
 *     roomToken: string,
 * }
 *
 * @psalm-type TalkFederationInvite = array{
 *     id: int,
 *     state: int,
 *     localCloudId: string,
 *     localToken: string,
 *     remoteAttendeeId: int,
 *     remoteServerUrl: string,
 *     remoteToken: string,
 *     roomName: string,
 *     userId: string,
 *     inviterCloudId: string,
 *     inviterDisplayName: string,
 * }
 *
 * @psalm-type TalkMatterbridgeConfigFields = list<array<string, mixed>>
 *
 * @psalm-type TalkMatterbridge = array{
 *     enabled: bool,
 *     parts: TalkMatterbridgeConfigFields,
 *     pid: int,
 * }
 *
 * @psalm-type TalkMatterbridgeProcessState = array{
 *     log: string,
 *     running: bool,
 * }
 *
 * @psalm-type TalkMatterbridgeWithProcessState = TalkMatterbridge&TalkMatterbridgeProcessState
 *
 * @psalm-type TalkParticipant = array{
 *     actorId: string,
 *     invitedActorId?: string,
 *     actorType: string,
 *     attendeeId: int,
 *     attendeePermissions: int,
 *     attendeePin: string,
 *     displayName: string,
 *     inCall: int,
 *     lastPing: int,
 *     participantType: int,
 *     permissions: int,
 *     roomToken: string,
 *     sessionIds: list<string>,
 *     status?: string,
 *     statusClearAt?: ?int,
 *     statusIcon?: ?string,
 *     statusMessage?: ?string,
 *     phoneNumber?: ?string,
 *     callId?: ?string,
 * }
 *
 * @psalm-type TalkPollVote = array{
 *     actorDisplayName: string,
 *     actorId: string,
 *     actorType: string,
 *     optionId: int,
 *  }
 *
 * @psalm-type TalkPollDraft = array{
 *     actorDisplayName: string,
 *     actorId: non-empty-string,
 *     actorType: TalkActorTypes,
 *     id: int<1, max>,
 *     maxVotes: int<0, max>,
 *     options: list<string>,
 *     question: non-empty-string,
 *     resultMode: 0|1,
 *     status: 0|1|2,
 * }
 *
 * @psalm-type TalkPoll = TalkPollDraft&array{
 *     details?: list<TalkPollVote>,
 *     numVoters?: int<0, max>,
 *     votedSelf?: list<int>,
 *     votes?: array<string, int>,
 * }
 *
 * @psalm-type TalkReaction = array{
 *     actorDisplayName: string,
 *     actorId: string,
 *     actorType: string,
 *     timestamp: int,
 * }
 *
 * @psalm-type TalkInvitationList = array{
 *     users?: list<string>,
 *     federated_users?: list<string>,
 *     groups?: list<string>,
 *     emails?: list<string>,
 *     phones?: list<string>,
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
 *     // Numeric identifier of the conversation
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
 *     // Listable scope for the room (only available with `listable-rooms` capability)
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
 *     notificationCalls: int,
 *     // The notification level for the user (See [Participant notification levels](https://nextcloud-talk.readthedocs.io/en/latest/constants#participant-notification-levels))
 *     notificationLevel: int,
 *     // See [Object types](https://nextcloud-talk.readthedocs.io/en/latest/constants#object-types) documentation for explanation
 *     objectId: string,
 *     // The type of object that the conversation is associated with (See [Object types](https://nextcloud-talk.readthedocs.io/en/latest/constants#object-types))
 *     objectType: string,
 *     // "In call" flags of the user's session making the request (only available with `in-call-flags` capability)
 *     participantFlags: int,
 * 	   // Permissions level of the current user
 *     participantType: int,
 *     // Combined final permissions for the current participant, permissions are picked in order of attendee then call then default and the first which is `Custom` will apply (see [constants list](https://nextcloud-talk.readthedocs.io/en/latest/constants#attendee-permissions))
 *     permissions: int,
 *     // Read-only state for the current user (only available with `read-only-rooms` capability)
 *     readOnly: int,
 *     // Whether recording consent is required before joining a call (Only 0 and 1 will be returned, see [constants list](https://nextcloud-talk.readthedocs.io/en/latest/constants#recording-consent-required)) (only available with `recording-consent` capability)
 *     recordingConsent: int,
 *     remoteServer?: string,
 *     remoteToken?: string,
 *     // `'0'` if not connected, otherwise an up to 512 character long string that is the identifier of the user's session making the request. Should only be used to pre-check if the user joined already with this session, but this might be outdated by the time of usage, so better check via [Get list of participants in a conversation](https://nextcloud-talk.readthedocs.io/en/latest/participant/#get-list-of-participants-in-a-conversation)
 *     sessionId: string,
 *     // SIP enable status (see [constants list](https://nextcloud-talk.readthedocs.io/en/latest/constants#sip-states))
 *     sipEnabled: int,
 *     // Optional: Only available for one-to-one conversations, when `includeStatus=true` is set and the user has a status
 *     status?: string,
 *     // Optional: Only available for one-to-one conversations, when `includeStatus=true` is set and the user has a status, can still be null even with a status
 *     statusClearAt?: ?int,
 *     // Optional: Only available for one-to-one conversations, when `includeStatus=true` is set and the user has a status, can still be null even with a status
 *     statusIcon?: ?string,
 *     // Optional: Only available for one-to-one conversations, when `includeStatus=true` is set and the user has a status, can still be null even with a status
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
 * }
 *
 * @psalm-type TalkDashboardEventAttachment = array{
 *      calendars: non-empty-list<string>,
 *      fmttype: string,
 *      filename: string,
 *      fileid: int,
 *      preview: boolean,
 *      previewLink: ?string,
 * }
 *
 * @psalm-type TalkDashboardEventCalendar = array{
 *     principalUri: string,
 *     calendarName: string,
 *     calendarColor: ?string,
 * }
 *
 * @psalm-type TalkDashboardEvent = array{
 *     calendars: non-empty-list<TalkDashboardEventCalendar>,
 *     eventName: string,
 *     eventDescription: ?string,
 *     eventAttachments: array<string, TalkDashboardEventAttachment>,
 *     eventLink: string,
 *     start: int,
 *     end: int,
 *     roomToken: string,
 *     roomAvatarVersion: string,
 *     roomName: string,
 *     roomDisplayName: string,
 *     roomType: int,
 *     roomActiveSince: ?int,
 *     invited: ?int,
 *     accepted: ?int,
 *     tentative: ?int,
 *     declined: ?int,
 * }
 *
 * @psalm-type TalkRoomWithInvalidInvitations = TalkRoom&array{
 *     invalidParticipants: TalkInvitationList,
 * }
 *
 * @psalm-type TalkSignalingSession = array{
 *     actorId: string,
 *     actorType: string,
 *     inCall: int,
 *     lastPing: int,
 *     participantPermissions: int,
 *     roomId: int,
 *     sessionId: string,
 *     userId: string,
 * }
 *
 * @psalm-type TalkSignalingFederationSettings = array{
 *     server: string,
 *     nextcloudServer: string,
 *     helloAuthParams: array{
 *         token: string,
 *     },
 *     roomId: string,
 * }
 *
 * @psalm-type TalkSignalingSettings = array{
 *     federation: TalkSignalingFederationSettings|null,
 *     helloAuthParams: array{
 *         "1.0": array{
 *             userid: ?string,
 *             ticket: string,
 *         },
 *         "2.0": array{
 *             token: string,
 *         },
 *     },
 *     hideWarning: bool,
 *     server: string,
 *     signalingMode: string,
 *     sipDialinInfo: string,
 *     stunservers: list<array{urls: list<string>}>,
 *     ticket: string,
 *     turnservers: list<array{urls: list<string>, username: string, credential: mixed}>,
 *     userId: ?string,
 * }
 *
 * @psalm-type TalkThread = array{
 *     id: positive-int,
 *     roomToken: string,
 *     title: string,
 *     lastMessageId: non-negative-int,
 *     lastActivity: non-negative-int,
 *     numReplies: non-negative-int,
 * }
 *
 * @psalm-type TalkThreadAttendee = array{
 *      notificationLevel: 0|1|2|3,
 * }
 *
 * @psalm-type TalkThreadInfo = array{
 *      thread: TalkThread,
 *      attendee: TalkThreadAttendee,
 *      first: ?TalkChatMessage,
 *      last: ?TalkChatMessage,
 * }
 *
 * @psalm-type TalkCapabilities = array{
 *     features: non-empty-list<string>,
 *     features-local: non-empty-list<string>,
 *     config: array{
 *         attachments: array{
 *             allowed: bool,
 *             folder?: string,
 *         },
 *         call: array{
 *             enabled: bool,
 *             breakout-rooms: bool,
 *             recording: bool,
 *             recording-consent: int,
 *             supported-reactions: list<string>,
 *             // List of file names relative to the spreed/img/backgrounds/ web path, e.g. `2_home.jpg`
 *             predefined-backgrounds: list<string>,
 *              // List of file paths relative to the server web root with leading slash, e.g. `/apps/spreed/img/backgrounds/2_home.jpg`
 *             predefined-backgrounds-v2: list<string>,
 *             can-upload-background: bool,
 *             sip-enabled: bool,
 *             sip-dialout-enabled: bool,
 *             can-enable-sip: bool,
 *             start-without-media: bool,
 *             max-duration: int,
 *             blur-virtual-background: bool,
 *             end-to-end-encryption: bool,
 *             live-transcription: bool,
 *         },
 *         chat: array{
 *             max-length: int,
 *             read-privacy: int,
 *             has-translation-providers: bool,
 *             has-translation-task-providers: bool,
 *             typing-privacy: int,
 *             summary-threshold: positive-int,
 *         },
 *         conversations: array{
 *             can-create: bool,
 *             force-passwords: bool,
 *             list-style: 'two-lines'|'compact',
 *             description-length: positive-int,
 *             retention-event: non-negative-int,
 *             retention-phone: non-negative-int,
 *             retention-instant-meetings: non-negative-int,
 *         },
 *         federation: array{
 *             enabled: bool,
 *             incoming-enabled: bool,
 *             outgoing-enabled: bool,
 *             only-trusted-servers: bool,
 *         },
 *         previews: array{
 *             max-gif-size: int,
 *         },
 *         signaling: array{
 *             session-ping-limit: int,
 *             hello-v2-token-key?: string,
 *         },
 *         experiments: array{
 *             enabled: non-negative-int,
 *         },
 *     },
 *     config-local: array<string, non-empty-list<string>>,
 *     version: string,
 * }
 *
 * @psalm-type TalkLiveTranscriptionLanguage = array{
 *     name: string,
 *     metadata: array{
 *         separator: string,
 *         rtl: bool,
 *     },
 * }
 */
class ResponseDefinitions {
}
